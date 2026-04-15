<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Track;
use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Assessment;
use App\Models\Question;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TrackEnrollment;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    /**
     * Display the classroom management dashboard
     */
    public function index()
    {
        // Redirect to the new classroom course controller
        return redirect()->route('admin.classroom.courses.index');
    }

    /**
     * Display progress dashboard
     */
    public function progressDashboard()
    {
        $progressStats = [
            'total_enrollments' => TrackEnrollment::count(),
            'active_learners' => TrackEnrollment::whereNull('completed_at')->count(),
            'completed_tracks' => TrackEnrollment::whereNotNull('completed_at')->count(),
            'total_lesson_completions' => LessonProgress::whereNotNull('completed_at')->count(),
        ];

        $trackProgress = Track::withCount([
            'enrollments',
            'enrollments as completed_enrollments_count' => function ($query) {
                $query->whereNotNull('completed_at');
            }
        ])->get();

        return Inertia::render('admin/classroom/ProgressDashboard', [
            'progressStats' => $progressStats,
            'trackProgress' => $trackProgress,
        ]);
    }

    /**
     * Display certificate manager
     */
    public function certificateManager(Request $request)
    {
        $query = Certificate::with(['user', 'track']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('track', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            })->orWhere('certificate_number', 'like', "%{$search}%");
        }

        if ($request->filled('track_id')) {
            $query->whereHas('track', function ($q) use ($request) {
                $q->where('id', $request->get('track_id'));
            });
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'valid') {
                $query->where('is_valid', true);
            } elseif ($status === 'revoked') {
                $query->where('is_valid', false);
            }
        }

        $certificates = $query->latest()->paginate(20);

        // Get templates and tracks for the interface
        $templates = CertificateTemplate::orderBy('name')->get();
        $tracks = Track::select('id', 'title')->orderBy('title')->get();

        return Inertia::render('admin/classroom/CertificateManager', [
            'certificates' => $certificates,
            'templates' => $templates,
            'tracks' => $tracks,
            'filters' => $request->only(['search', 'track_id', 'status']),
        ]);
    }

    /**
     * Download a certificate
     */
    public function downloadCertificate(Certificate $certificate)
    {
        // Update download count and timestamp
        $certificate->increment('download_count');
        $certificate->update(['downloaded_at' => now()]);

        // Here you would generate and return the PDF certificate
        // For now, return a simple response
        return response()->json(['message' => 'Certificate download started']);
    }

    /**
     * Resend a certificate to the user
     */
    public function resendCertificate(Certificate $certificate)
    {
        // Here you would send the certificate via email
        // For now, return a simple response
        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Certificate resent successfully.');
    }

    /**
     * Revoke a certificate
     */
    public function revokeCertificate(Certificate $certificate)
    {
        $certificate->update(['is_valid' => false]);

        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Certificate revoked successfully.');
    }

    /**
     * Bulk generate certificates
     */
    public function bulkGenerateCertificates(Request $request)
    {
        $validated = $request->validate([
            'track_id' => 'required|exists:tracks,id',
            'template_id' => 'nullable|exists:certificate_templates,id',
        ]);

        // Here you would implement bulk certificate generation logic
        // For now, return a simple response
        return redirect()->route('admin.classroom.certificates')
            ->with('success', 'Bulk certificate generation started.');
    }
}
