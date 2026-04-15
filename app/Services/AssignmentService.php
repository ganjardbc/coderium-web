<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class AssignmentService
{
    /**
     * Create a new assignment.
     *
     * @param array $data
     * @return Assignment
     * @throws ValidationException
     */
    public function createAssignment(array $data): Assignment
    {
        $this->validateAssignmentData($data);

        return DB::transaction(function () use ($data) {
            // Process evaluation checklist
            if (isset($data['evaluation_checklist']) && is_string($data['evaluation_checklist'])) {
                $data['evaluation_checklist'] = $this->processEvaluationChecklist($data['evaluation_checklist']);
            }

            return Assignment::create($data);
        });
    }

    /**
     * Update an assignment.
     *
     * @param Assignment $assignment
     * @param array $data
     * @return Assignment
     * @throws ValidationException
     */
    public function updateAssignment(Assignment $assignment, array $data): Assignment
    {
        $this->validateAssignmentData($data, $assignment->id);

        return DB::transaction(function () use ($assignment, $data) {
            // Process evaluation checklist
            if (isset($data['evaluation_checklist']) && is_string($data['evaluation_checklist'])) {
                $data['evaluation_checklist'] = $this->processEvaluationChecklist($data['evaluation_checklist']);
            }

            $assignment->update($data);
            return $assignment->fresh();
        });
    }

    /**
     * Submit an assignment.
     *
     * @param Assignment $assignment
     * @param User $user
     * @param array $data
     * @param array $files
     * @return AssignmentSubmission
     * @throws ValidationException
     */
    public function submitAssignment(Assignment $assignment, User $user, array $data, array $files = []): AssignmentSubmission
    {
        // Add files to data for validation
        $data['files'] = $files;
        $this->validateSubmissionData($data, $assignment, $user);

        return DB::transaction(function () use ($assignment, $user, $data, $files) {
            // Handle file uploads
            $fileAttachments = [];
            if (!empty($files)) {
                $fileAttachments = $this->handleFileUploads($files, $assignment, $user);
            }

            // Prepare submission data
            $submissionData = [
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'repository_url' => $data['repository_url'] ?? null,
                'file_attachments' => $fileAttachments,
                'submission_notes' => $data['submission_notes'] ?? null,
                'submitted_at' => now(),
            ];

            // Check if user already has a submission
            $existingSubmission = $assignment->getSubmissionForUser($user);

            if ($existingSubmission) {
                // Update existing submission if not graded
                if (!$existingSubmission->isGraded()) {
                    // Clean up old files if new ones are uploaded
                    if (!empty($fileAttachments) && $existingSubmission->file_attachments) {
                        $this->cleanupOldFiles($existingSubmission->file_attachments);
                    }

                    $existingSubmission->update($submissionData);
                    $submission = $existingSubmission;
                } else {
                    throw ValidationException::withMessages([
                        'submission' => 'Cannot resubmit a graded assignment.',
                    ]);
                }
            } else {
                $submission = AssignmentSubmission::create($submissionData);
            }

            // Send notification to instructors
            $this->sendSubmissionNotifications($submission);

            return $submission;
        });
    }

    /**
     * Grade an assignment submission.
     *
     * @param AssignmentSubmission $submission
     * @param array $data
     * @return AssignmentSubmission
     * @throws ValidationException
     */
    public function gradeSubmission(AssignmentSubmission $submission, array $data): AssignmentSubmission
    {
        $this->validateGradingData($data);

        return DB::transaction(function () use ($submission, $data) {
            $submission->update([
                'grade' => $data['grade'],
                'feedback' => $data['feedback'] ?? null,
                'graded_at' => now(),
            ]);

            // Send notification to student
            $this->sendGradingNotifications($submission);

            return $submission->fresh();
        });
    }

    /**
     * Get assignments for a module.
     *
     * @param Module $module
     * @param bool $publishedOnly
     * @return Collection
     */
    public function getAssignmentsForModule(Module $module, bool $publishedOnly = true): Collection
    {
        $query = $module->assignments();

        if ($publishedOnly) {
            $query->where('is_published', true);
        }

        return $query->orderBy('due_date')->get();
    }

    /**
     * Get assignment with user submission status.
     *
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    public function getAssignmentWithUserStatus(Assignment $assignment, User $user): array
    {
        $submission = $assignment->getSubmissionForUser($user);

        return [
            'assignment' => $assignment,
            'submission' => $submission,
            'has_submitted' => $submission !== null,
            'is_graded' => $submission && $submission->isGraded(),
            'is_overdue' => $assignment->isOverdue(),
            'can_submit' => !$assignment->isOverdue() && (!$submission || !$submission->isGraded()),
            'days_until_due' => $assignment->due_date ? (int) now()->diffInDays($assignment->due_date, false) : null,
        ];
    }

    /**
     * Get submissions for grading.
     *
     * @param Assignment $assignment
     * @param array $filters
     * @return Collection
     */
    public function getSubmissionsForGrading(Assignment $assignment, array $filters = []): Collection
    {
        $query = $assignment->submissions()->with('user');

        // Apply filters
        if (isset($filters['graded']) && $filters['graded'] !== null) {
            if ($filters['graded']) {
                $query->whereNotNull('graded_at');
            } else {
                $query->whereNull('graded_at');
            }
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->where('submitted_at', '>', $assignment->due_date);
        }

        return $query->orderBy('submitted_at')->get();
    }

    /**
     * Get assignment statistics.
     *
     * @param Assignment $assignment
     * @return array
     */
    public function getAssignmentStatistics(Assignment $assignment): array
    {
        $submissions = $assignment->submissions;
        $totalEnrolled = $this->getTotalEnrolledUsers($assignment);

        $gradedSubmissions = $submissions->whereNotNull('graded_at');
        $overdueSubmissions = $submissions->where('submitted_at', '>', $assignment->due_date);

        return [
            'total_enrolled' => $totalEnrolled,
            'total_submissions' => $submissions->count(),
            'submission_rate' => $totalEnrolled > 0 ? round(($submissions->count() / $totalEnrolled) * 100, 2) : 0,
            'graded_submissions' => $gradedSubmissions->count(),
            'pending_grading' => $submissions->count() - $gradedSubmissions->count(),
            'overdue_submissions' => $overdueSubmissions->count(),
            'average_grade' => $gradedSubmissions->count() > 0 ? round($gradedSubmissions->avg('grade'), 2) : null,
            'grade_distribution' => $this->getGradeDistribution($gradedSubmissions),
            'submission_timeline' => $this->getSubmissionTimeline($submissions, $assignment->due_date),
        ];
    }

    /**
     * Bulk grade submissions using evaluation checklist.
     *
     * @param Assignment $assignment
     * @param array $gradingData
     * @return array
     */
    public function bulkGradeSubmissions(Assignment $assignment, array $gradingData): array
    {
        $results = [];

        DB::transaction(function () use ($assignment, $gradingData, &$results) {
            foreach ($gradingData as $submissionId => $gradeData) {
                $submission = AssignmentSubmission::find($submissionId);

                if ($submission && $submission->assignment_id === $assignment->id) {
                    try {
                        $gradedSubmission = $this->gradeSubmission($submission, $gradeData);
                        $results['success'][] = $gradedSubmission->id;
                    } catch (ValidationException $e) {
                        $results['errors'][$submissionId] = $e->getMessage();
                    }
                }
            }
        });

        return $results;
    }

    /**
     * Export assignment submissions.
     *
     * @param Assignment $assignment
     * @param string $format
     * @return string
     */
    public function exportSubmissions(Assignment $assignment, string $format = 'csv'): string
    {
        $submissions = $assignment->submissions()->with('user')->get();

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($submissions);
            case 'json':
                return $this->exportToJson($submissions);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Validate assignment data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateAssignmentData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'instructions' => 'required|string|max:5000',
            'evaluation_checklist' => 'nullable|string|max:3000',
            'due_date' => 'nullable|date|after:now',
            'is_published' => 'boolean',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate submission data.
     *
     * @param array $data
     * @param Assignment $assignment
     * @param User $user
     * @throws ValidationException
     */
    private function validateSubmissionData(array $data, Assignment $assignment, User $user): void
    {
        $rules = [
            'repository_url' => 'nullable|url|max:500',
            'submission_notes' => 'nullable|string|max:2000',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // At least one of repository_url or files must be provided
        if (empty($data['repository_url']) && empty($data['files'])) {
            throw ValidationException::withMessages([
                'repository_url' => 'Either a repository URL or file attachments must be provided.',
            ]);
        }

        // Check if assignment is still accepting submissions
        if ($assignment->isOverdue()) {
            throw ValidationException::withMessages([
                'assignment' => 'Assignment submission deadline has passed.',
            ]);
        }

        // Check if user is enrolled in the track
        $this->validateUserEnrollment($user, $assignment);
    }

    /**
     * Validate grading data.
     *
     * @param array $data
     * @throws ValidationException
     */
    private function validateGradingData(array $data): void
    {
        $rules = [
            'grade' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string|max:3000',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate user enrollment for assignment access.
     *
     * @param User $user
     * @param Assignment $assignment
     * @throws ValidationException
     */
    private function validateUserEnrollment(User $user, Assignment $assignment): void
    {
        $track = $assignment->module->level->track;

        $isEnrolled = $track->enrollments()
            ->where('user_id', $user->id)
            ->exists();

        if (!$isEnrolled) {
            throw ValidationException::withMessages([
                'enrollment' => 'User must be enrolled in the track to submit assignments.',
            ]);
        }
    }

    /**
     * Process evaluation checklist from string to array.
     *
     * @param string $checklist
     * @return array
     */
    private function processEvaluationChecklist(string $checklist): array
    {
        // Split by lines and create checklist items
        $lines = array_filter(array_map('trim', explode("\n", $checklist)));
        $processedChecklist = [];

        foreach ($lines as $line) {
            // Remove markdown-style checkboxes and bullets
            $line = preg_replace('/^[-*]\s*(\[[ x]\])?\s*/', '', $line);

            if (!empty($line)) {
                $processedChecklist[] = [
                    'item' => $line,
                    'points' => 1, // Default points per item
                ];
            }
        }

        return $processedChecklist;
    }

    /**
     * Handle file uploads for assignment submission.
     *
     * @param array $files
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    private function handleFileUploads(array $files, Assignment $assignment, User $user): array
    {
        $uploadedFiles = [];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png'];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                // Validate file
                if ($file->getSize() > $maxFileSize) {
                    throw ValidationException::withMessages([
                        'files' => "File {$file->getClientOriginalName()} exceeds maximum size of 10MB.",
                    ]);
                }

                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, $allowedExtensions)) {
                    throw ValidationException::withMessages([
                        'files' => "File type {$extension} is not allowed.",
                    ]);
                }

                // Store file
                $path = $file->store(
                    "assignments/{$assignment->id}/submissions/{$user->id}",
                    'private'
                );

                $uploadedFiles[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_at' => now()->toISOString(),
                ];
            }
        }

        return $uploadedFiles;
    }

    /**
     * Clean up old uploaded files.
     *
     * @param array $fileAttachments
     * @return void
     */
    private function cleanupOldFiles(array $fileAttachments): void
    {
        foreach ($fileAttachments as $file) {
            if (isset($file['path']) && Storage::disk('private')->exists($file['path'])) {
                Storage::disk('private')->delete($file['path']);
            }
        }
    }

    /**
     * Get total enrolled users for assignment's track.
     *
     * @param Assignment $assignment
     * @return int
     */
    private function getTotalEnrolledUsers(Assignment $assignment): int
    {
        return $assignment->module->level->track->enrollments()->count();
    }

    /**
     * Get grade distribution for submissions.
     *
     * @param Collection $gradedSubmissions
     * @return array
     */
    private function getGradeDistribution(Collection $gradedSubmissions): array
    {
        if ($gradedSubmissions->isEmpty()) {
            return [];
        }

        $ranges = [
            'A (90-100)' => [90, 100],
            'B (80-89)' => [80, 89],
            'C (70-79)' => [70, 79],
            'D (60-69)' => [60, 69],
            'F (0-59)' => [0, 59],
        ];

        $distribution = [];
        foreach ($ranges as $label => $range) {
            $count = $gradedSubmissions->whereBetween('grade', $range)->count();
            $distribution[$label] = [
                'count' => $count,
                'percentage' => round(($count / $gradedSubmissions->count()) * 100, 1),
            ];
        }

        return $distribution;
    }

    /**
     * Get submission timeline data.
     *
     * @param Collection $submissions
     * @param Carbon|null $dueDate
     * @return array
     */
    private function getSubmissionTimeline(Collection $submissions, ?Carbon $dueDate): array
    {
        if ($submissions->isEmpty()) {
            return [];
        }

        $timeline = [];
        $submissions->groupBy(function ($submission) {
            return $submission->submitted_at->format('Y-m-d');
        })->each(function ($daySubmissions, $date) use (&$timeline, $dueDate) {
            $timeline[] = [
                'date' => $date,
                'count' => $daySubmissions->count(),
                'is_after_due' => $dueDate && Carbon::parse($date)->isAfter($dueDate),
            ];
        });

        return collect($timeline)->sortBy('date')->values()->toArray();
    }

    /**
     * Export submissions to CSV format.
     *
     * @param Collection $submissions
     * @return string
     */
    private function exportToCsv(Collection $submissions): string
    {
        $csv = "Student Name,Email,Submitted At,Grade,Repository URL,Feedback\n";

        foreach ($submissions as $submission) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $submission->user->name ?? 'N/A',
                $submission->user->email ?? 'N/A',
                $submission->submitted_at ? $submission->submitted_at->format('Y-m-d H:i:s') : 'N/A',
                $submission->grade ?? 'Not Graded',
                $submission->repository_url ?? 'N/A',
                str_replace(["\n", "\r", '"'], [' ', ' ', '""'], $submission->feedback ?? 'N/A')
            );
        }

        return $csv;
    }

    /**
     * Export submissions to JSON format.
     *
     * @param Collection $submissions
     * @return string
     */
    private function exportToJson(Collection $submissions): string
    {
        $data = $submissions->map(function ($submission) {
            return [
                'student_name' => $submission->user->name ?? null,
                'student_email' => $submission->user->email ?? null,
                'submitted_at' => $submission->submitted_at ? $submission->submitted_at->toISOString() : null,
                'grade' => $submission->grade,
                'repository_url' => $submission->repository_url,
                'feedback' => $submission->feedback,
                'file_attachments' => $submission->file_attachments,
                'submission_notes' => $submission->submission_notes,
            ];
        });

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Send notifications for new submissions.
     *
     * @param AssignmentSubmission $submission
     * @return void
     */
    private function sendSubmissionNotifications(AssignmentSubmission $submission): void
    {
        // Get instructors for the track
        $track = $submission->assignment->module->level->track;
        $instructors = User::where('role', 'instructor')->get();

        // Send notifications (implement notification classes as needed)
        // Notification::send($instructors, new NewAssignmentSubmissionNotification($submission));
    }

    /**
     * Send notifications for grading.
     *
     * @param AssignmentSubmission $submission
     * @return void
     */
    private function sendGradingNotifications(AssignmentSubmission $submission): void
    {
        // Send notification to student
        // Notification::send($submission->user, new AssignmentGradedNotification($submission));
    }
}
