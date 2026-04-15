<?php

namespace App\Services;

use App\Models\User;
use App\Models\Track;
use App\Models\Course;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TrackEnrollment;
use App\Models\CourseEnrollment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    /**
     * Generate certificate with dynamic template selection.
     *
     * @param User $user
     * @param mixed $certifiable
     * @param array $options
     * @return Certificate
     * @throws ValidationException
     */
    public function generateCertificate(User $user, $certifiable, array $options = []): Certificate
    {
        // Validate certificate generation requirements
        $this->validateCertificateGeneration($user, $certifiable);

        // Check for existing certificate to prevent duplicates
        $existingCertificate = $this->getExistingCertificate($user, $certifiable);
        if ($existingCertificate) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificate already exists for this user and learning path.',
            ]);
        }

        // Select appropriate template
        $template = $this->selectTemplate($certifiable, $options['template_id'] ?? null);

        // Generate certificate data
        $certificateData = $this->generateCertificateData($user, $certifiable, $template);

        return DB::transaction(function () use ($user, $certifiable, $template, $certificateData) {
            $certificate = Certificate::create([
                'user_id' => $user->id,
                'certifiable_type' => get_class($certifiable),
                'certifiable_id' => $certifiable->id,
                'template_id' => $template->id,
                'certificate_number' => Certificate::generateCertificateNumber(),
                'title' => $certificateData['title'],
                'description' => $certificateData['description'],
                'issued_at' => now(),
                'completed_at' => $certificateData['completed_at'],
                'metadata' => $certificateData['metadata'],
                'is_valid' => true,
            ]);

            // Generate verification URL
            $certificate->update([
                'verification_url' => $certificate->generateVerificationUrl(),
            ]);

            return $certificate;
        });
    }

    /**
     * Select template method for polymorphic certificate types.
     *
     * @param mixed $certifiable
     * @param int|null $templateId
     * @return CertificateTemplate
     * @throws ValidationException
     */
    public function selectTemplate($certifiable, ?int $templateId = null): CertificateTemplate
    {
        // If specific template ID is provided, use it
        if ($templateId) {
            $template = CertificateTemplate::find($templateId);
            if (!$template) {
                throw ValidationException::withMessages([
                    'template' => 'Specified certificate template not found.',
                ]);
            }
            return $template;
        }

        // Dynamic template selection based on certifiable type
        if ($certifiable instanceof Track) {
            return $this->selectTrackTemplate($certifiable);
        } elseif ($certifiable instanceof Course) {
            return $this->selectCourseTemplate($certifiable);
        }

        throw ValidationException::withMessages([
            'certifiable' => 'Unsupported certifiable type for certificate generation.',
        ]);
    }

    /**
     * Get existing certificate for user and certifiable entity.
     *
     * @param User $user
     * @param mixed $certifiable
     * @return Certificate|null
     */
    public function getExistingCertificate(User $user, $certifiable): ?Certificate
    {
        return Certificate::where('user_id', $user->id)
            ->where('certifiable_type', get_class($certifiable))
            ->where('certifiable_id', $certifiable->id)
            ->first();
    }

    /**
     * Get all certificates for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getUserCertificates(User $user): Collection
    {
        return Certificate::where('user_id', $user->id)
            ->where('is_valid', true)
            ->with(['certifiable', 'template'])
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    /**
     * Verify certificate by certificate number.
     *
     * @param string $certificateNumber
     * @return Certificate|null
     */
    public function verifyCertificate(string $certificateNumber): ?Certificate
    {
        return Certificate::where('certificate_number', $certificateNumber)
            ->where('is_valid', true)
            ->with(['user', 'certifiable', 'template'])
            ->first();
    }

    /**
     * Revoke a certificate.
     *
     * @param Certificate $certificate
     * @param string $reason
     * @return bool
     */
    public function revokeCertificate(Certificate $certificate, string $reason = ''): bool
    {
        return $certificate->update([
            'is_valid' => false,
            'metadata' => array_merge($certificate->metadata ?? [], [
                'revoked_at' => now()->toISOString(),
                'revocation_reason' => $reason,
            ]),
        ]);
    }

    /**
     * Regenerate certificate (for template updates or corrections).
     *
     * @param Certificate $certificate
     * @param array $options
     * @return Certificate
     */
    public function regenerateCertificate(Certificate $certificate, array $options = []): Certificate
    {
        $user = $certificate->user;
        $certifiable = $certificate->certifiable;

        // Revoke old certificate
        $this->revokeCertificate($certificate, 'Regenerated');

        // Generate new certificate
        return $this->generateCertificate($user, $certifiable, $options);
    }

    /**
     * Check if user is eligible for certificate.
     *
     * @param User $user
     * @param mixed $certifiable
     * @return bool
     */
    public function isEligibleForCertificate(User $user, $certifiable): bool
    {
        if ($certifiable instanceof Track) {
            $enrollment = TrackEnrollment::where('user_id', $user->id)
                ->where('track_id', $certifiable->id)
                ->first();

            return $enrollment && $enrollment->isCompleted();
        } elseif ($certifiable instanceof Course) {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $certifiable->id)
                ->first();

            return $enrollment && $enrollment->isCompleted();
        }

        return false;
    }

    /**
     * Get certificate statistics for a learning path.
     *
     * @param mixed $certifiable
     * @return array
     */
    public function getCertificateStatistics($certifiable): array
    {
        $totalCertificates = Certificate::where('certifiable_type', get_class($certifiable))
            ->where('certifiable_id', $certifiable->id)
            ->where('is_valid', true)
            ->count();

        $recentCertificates = Certificate::where('certifiable_type', get_class($certifiable))
            ->where('certifiable_id', $certifiable->id)
            ->where('is_valid', true)
            ->where('issued_at', '>=', now()->subDays(30))
            ->count();

        return [
            'total_certificates' => $totalCertificates,
            'recent_certificates' => $recentCertificates,
            'certificate_type' => $certifiable instanceof Track ? 'track' : 'course',
        ];
    }

    /**
     * Validate certificate generation requirements.
     *
     * @param User $user
     * @param mixed $certifiable
     * @throws ValidationException
     */
    private function validateCertificateGeneration(User $user, $certifiable): void
    {
        // Check if user is eligible for certificate
        if (!$this->isEligibleForCertificate($user, $certifiable)) {
            throw ValidationException::withMessages([
                'eligibility' => 'User is not eligible for certificate. Learning path must be completed.',
            ]);
        }

        // Validate certifiable entity
        if (!$certifiable || !$certifiable->exists) {
            throw ValidationException::withMessages([
                'certifiable' => 'Invalid certifiable entity.',
            ]);
        }

        // Check if certifiable is published/active
        if ($certifiable instanceof Track && !$certifiable->is_published) {
            throw ValidationException::withMessages([
                'track' => 'Cannot generate certificate for unpublished track.',
            ]);
        } elseif ($certifiable instanceof Course && !$certifiable->is_active) {
            throw ValidationException::withMessages([
                'course' => 'Cannot generate certificate for inactive course.',
            ]);
        }
    }

    /**
     * Select template for track certificates.
     *
     * @param Track $track
     * @return CertificateTemplate
     * @throws ValidationException
     */
    private function selectTrackTemplate(Track $track): CertificateTemplate
    {
        // Try to get track-specific template first
        if ($track->certificate_template_id) {
            $template = CertificateTemplate::find($track->certificate_template_id);
            if ($template) {
                return $template;
            }
        }

        // Fall back to default track template
        $defaultTemplate = CertificateTemplate::where('is_default', true)
            ->where('name', 'LIKE', '%track%')
            ->first();

        if ($defaultTemplate) {
            return $defaultTemplate;
        }

        // Fall back to any default template
        $anyDefault = CertificateTemplate::where('is_default', true)->first();

        if (!$anyDefault) {
            throw ValidationException::withMessages([
                'template' => 'No certificate template available for track certificates.',
            ]);
        }

        return $anyDefault;
    }

    /**
     * Select template for course certificates.
     *
     * @param Course $course
     * @return CertificateTemplate
     * @throws ValidationException
     */
    private function selectCourseTemplate(Course $course): CertificateTemplate
    {
        // Try to get course-specific template first
        if ($course->certificate_template_id) {
            $template = CertificateTemplate::find($course->certificate_template_id);
            if ($template) {
                return $template;
            }
        }

        // Fall back to default course template
        $defaultTemplate = CertificateTemplate::where('is_default', true)
            ->where('name', 'LIKE', '%course%')
            ->first();

        if ($defaultTemplate) {
            return $defaultTemplate;
        }

        // Fall back to any default template
        $anyDefault = CertificateTemplate::where('is_default', true)->first();

        if (!$anyDefault) {
            throw ValidationException::withMessages([
                'template' => 'No certificate template available for course certificates.',
            ]);
        }

        return $anyDefault;
    }

    /**
     * Generate certificate data for the certificate.
     *
     * @param User $user
     * @param mixed $certifiable
     * @param CertificateTemplate $template
     * @return array
     */
    private function generateCertificateData(User $user, $certifiable, CertificateTemplate $template): array
    {
        $completedAt = null;
        $learningPathType = '';
        $learningPathTitle = '';

        if ($certifiable instanceof Track) {
            $enrollment = TrackEnrollment::where('user_id', $user->id)
                ->where('track_id', $certifiable->id)
                ->first();
            $completedAt = $enrollment?->completed_at;
            $learningPathType = 'Track';
            $learningPathTitle = $certifiable->title;
        } elseif ($certifiable instanceof Course) {
            $enrollment = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $certifiable->id)
                ->first();
            $completedAt = $enrollment?->completed_at;
            $learningPathType = 'Course';
            $learningPathTitle = $certifiable->title;
        }

        return [
            'title' => "Certificate of Completion - {$learningPathTitle}",
            'description' => "This certifies that {$user->name} has successfully completed the {$learningPathType}: {$learningPathTitle}",
            'completed_at' => $completedAt ?? now(),
            'metadata' => [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'learning_path_type' => $learningPathType,
                'learning_path_title' => $learningPathTitle,
                'learning_path_id' => $certifiable->id,
                'template_name' => $template->name,
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
}
