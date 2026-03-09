<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Track;
use App\Models\Course;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certificateService
    ) {}

    /**
     * Display a listing of certificates.
     */
    public function index(Request $request)
    {
        $query = Certificate::with(['user', 'certifiable', 'template']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('certificate_number', 'like', "%{$search}%")
              ->orWhere('title', 'like', "%{$search}%");
        }

        if ($request->filled('certificate_type')) {
            $type = $request->get('certificate_type');
            if ($type === 'track') {
                $query->where('certifiable_type', 'App\\Models\\Track')
                      ->orWhere(function ($q) {
                          $q->whereNull('certifiable_type')->whereNotNull('track_id');
                      });
            } elseif ($type === 'course') {
                $query->where('certifiable_type', 'App\\Models\\Course');
            }
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'valid') {
                $query->where('is_valid', true);
            } elseif ($status === 'revoked') {
                $query->where('is_valid', false);
            }
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->get('template_id'));
        }

        $certificates = $query->latest('issued_at')->paginate(20);

        // Get filter options
        $templates = CertificateTemplate::orderBy('name')->get();
        $certificateTypes = [
            ['value' => 'track', 'label' => 'Track Certificates'],
            ['value' => 'course', 'label' => 'Course Certificates'],
        ];

        return Inertia::render('admin/classroom/CertificateIndex', [
            'certificates' => $certificates,
            'templates' => $templates,
            'certificateTypes' => $certificateTypes,
            'filters' => $request->only(['search', 'certificate_type', 'status', 'template_id']),
        ]);
    }

    /**
     * Show the form for creating a new certificate.
     */
    public function create()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        $tracks = Track::select('id', 'title')->where('is_published', true)->orderBy('title')->get();
        $courses = Course::select('id', 'title')->where('is_active', true)->orderBy('title')->get();
        $templates = CertificateTemplate::orderBy('name')->get();

        return Inertia::render('admin/classroom/CertificateCreate', [
            'users' => $users,
            'tracks' => $tracks,
            'courses' => $courses,
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created certificate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'certifiable_type' => 'required|in:App\\Models\\Track,App\\Models\\Course',
            'certifiable_id' => 'required|integer',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $user = User::findOrFail($validated['user_id']);
            $certifiableClass = $validated['certifiable_type'];
            $certifiable = $certifiableClass::findOrFail($validated['certifiable_id']);

            // Check if certificate already exists
            $existingCertificate = Certificate::where('user_id', $user->id)
                ->where('certifiable_type', $validated['certifiable_type'])
                ->where('certifiable_id', $validated['certifiable_id'])
                ->first();

            if ($existingCertificate) {
                return redirect()->back()
                    ->withErrors(['certificate' => 'Certificate already exists for this user and learning path.']);
            }

            $certificate = $this->certificateService->generateCertificate($user, $certifiable, [
                'template_id' => $validated['template_id'] ?? null,
                'title' => $validated['title'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            return redirect()->route('admin.classroom.certificates.index')
                ->with('success', 'Certificate created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['certificate' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified certificate.
     */
    public function show(Certificate $certificate)
    {
        $certificate->load(['user', 'certifiable', 'template']);

        return Inertia::render('admin/classroom/CertificateShow', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Update the specified certificate.
     */
    public function update(Request $request, Certificate $certificate)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_valid' => 'boolean',
        ]);

        $certificate->update($validated);

        return redirect()->route('admin.classroom.certificates.index')
            ->with('success', 'Certificate updated successfully.');
    }

    /**
     * Remove the specified certificate.
     */
    public function destroy(Certificate $certificate)
    {
        $certificate->delete();

        return redirect()->route('admin.classroom.certificates.index')
            ->with('success', 'Certificate deleted successfully.');
    }

    /**
     * Download a certificate.
     */
    public function download(Certificate $certificate)
    {
        // Update download count and timestamp
        $certificate->increment('download_count');
        $certificate->update(['downloaded_at' => now()]);

        // Here you would generate and return the PDF certificate
        // For now, return a simple response
        return response()->json([
            'success' => true,
            'message' => 'Certificate download started',
            'download_url' => '#', // Would be actual PDF URL
        ]);
    }

    /**
     * Resend a certificate to the user.
     */
    public function resend(Certificate $certificate)
    {
        try {
            // Here you would send the certificate via email
            // For now, return a simple response
            return redirect()->route('admin.classroom.certificates.index')
                ->with('success', 'Certificate resent successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.classroom.certificates.index')
                ->with('error', 'Failed to resend certificate: ' . $e->getMessage());
        }
    }

    /**
     * Revoke a certificate.
     */
    public function revoke(Certificate $certificate)
    {
        $certificate->update(['is_valid' => false]);

        return redirect()->route('admin.classroom.certificates.index')
            ->with('success', 'Certificate revoked successfully.');
    }

    /**
     * Restore a revoked certificate.
     */
    public function restore(Certificate $certificate)
    {
        $certificate->update(['is_valid' => true]);

        return redirect()->route('admin.classroom.certificates.index')
            ->with('success', 'Certificate restored successfully.');
    }

    /**
     * Bulk generate certificates for a learning path.
     */
    public function bulkGenerate(Request $request)
    {
        $validated = $request->validate([
            'certifiable_type' => 'required|in:App\\Models\\Track,App\\Models\\Course',
            'certifiable_id' => 'required|integer',
            'template_id' => 'nullable|exists:certificate_templates,id',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
            'generate_for_all_completed' => 'boolean',
        ]);

        try {
            $certifiableClass = $validated['certifiable_type'];
            $certifiable = $certifiableClass::findOrFail($validated['certifiable_id']);

            $users = collect();

            if ($validated['generate_for_all_completed'] ?? false) {
                // Get all users who completed this learning path
                if ($certifiable instanceof Track) {
                    $users = User::whereHas('trackEnrollments', function ($query) use ($certifiable) {
                        $query->where('track_id', $certifiable->id)
                              ->whereNotNull('completed_at');
                    })->get();
                } elseif ($certifiable instanceof Course) {
                    $users = User::whereHas('courseEnrollments', function ($query) use ($certifiable) {
                        $query->where('course_id', $certifiable->id)
                              ->whereNotNull('completed_at');
                    })->get();
                }
            } elseif (!empty($validated['user_ids'])) {
                $users = User::whereIn('id', $validated['user_ids'])->get();
            }

            $generated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($users as $user) {
                try {
                    // Check if certificate already exists
                    $existingCertificate = Certificate::where('user_id', $user->id)
                        ->where('certifiable_type', $validated['certifiable_type'])
                        ->where('certifiable_id', $validated['certifiable_id'])
                        ->first();

                    if ($existingCertificate) {
                        $skipped++;
                        continue;
                    }

                    $this->certificateService->generateCertificate($user, $certifiable, [
                        'template_id' => $validated['template_id'] ?? null,
                    ]);
                    $generated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to generate certificate for {$user->name}: {$e->getMessage()}";
                }
            }

            $message = "Bulk generation completed. Generated: {$generated}, Skipped: {$skipped}";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode(', ', $errors);
            }

            return redirect()->route('admin.classroom.certificates.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['bulk_generate' => $e->getMessage()]);
        }
    }

    /**
     * Get certificate statistics.
     */
    public function statistics()
    {
        $stats = [
            'total_certificates' => Certificate::count(),
            'valid_certificates' => Certificate::where('is_valid', true)->count(),
            'revoked_certificates' => Certificate::where('is_valid', false)->count(),
            'track_certificates' => Certificate::where('certifiable_type', 'App\\Models\\Track')
                ->orWhere(function ($q) {
                    $q->whereNull('certifiable_type')->whereNotNull('track_id');
                })->count(),
            'course_certificates' => Certificate::where('certifiable_type', 'App\\Models\\Course')->count(),
            'total_downloads' => Certificate::sum('download_count'),
        ];

        // Recent certificates
        $recentCertificates = Certificate::with(['user', 'certifiable'])
            ->latest('issued_at')
            ->take(10)
            ->get();

        // Top certificate templates
        $topTemplates = CertificateTemplate::withCount('certificates')
            ->orderBy('certificates_count', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_certificates' => $recentCertificates,
            'top_templates' => $topTemplates,
        ]);
    }
}
