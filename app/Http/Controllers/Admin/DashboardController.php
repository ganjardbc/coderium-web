<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function index(): Response
    {
        // Fetch analytics directly from database
        $analytics = app(\App\Http\Controllers\Api\AnalyticsController::class)->index()->getData(true);

        return Inertia::render('admin/Dashboard', [
            'analytics' => $analytics,
        ]);
    }
}
