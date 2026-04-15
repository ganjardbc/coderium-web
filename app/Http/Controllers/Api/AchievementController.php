<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\UserAchievement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->get('userId', auth()->id());

        if ($userId) {
            // Get user's achievements
            $achievements = UserAchievement::with(['achievement'])
                ->where('user_id', $userId)
                ->get()
                ->map(function ($userAchievement) {
                    return [
                        'id' => $userAchievement->achievement->id,
                        'title' => $userAchievement->achievement->title,
                        'description' => $userAchievement->achievement->description,
                        'icon' => $userAchievement->achievement->icon,
                        'points' => $userAchievement->achievement->points,
                        'earned_at' => $userAchievement->created_at,
                        'progress' => 100, // Already earned
                    ];
                });
        } else {
            // Get all available achievements
            $achievements = Achievement::all()->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'points' => $achievement->points,
                    'requirements' => $achievement->requirements,
                ];
            });
        }

        return response()->json($achievements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'achievement_id' => 'required|exists:achievements,id',
            'progress' => 'sometimes|numeric|min:0|max:100',
        ]);

        // Check if user already has this achievement
        $existingAchievement = UserAchievement::where('user_id', $validated['user_id'])
            ->where('achievement_id', $validated['achievement_id'])
            ->first();

        if ($existingAchievement) {
            return response()->json(['message' => 'Achievement already earned'], 409);
        }

        $userAchievement = UserAchievement::create([
            'user_id' => $validated['user_id'],
            'achievement_id' => $validated['achievement_id'],
            'earned_at' => now(),
        ]);

        $achievement = Achievement::find($validated['achievement_id']);

        return response()->json([
            'id' => $achievement->id,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'points' => $achievement->points,
            'earned_at' => $userAchievement->earned_at,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $achievement = Achievement::findOrFail($id);

        return response()->json([
            'id' => $achievement->id,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'points' => $achievement->points,
            'requirements' => $achievement->requirements,
            'total_earned' => UserAchievement::where('achievement_id', $id)->count(),
        ]);
    }
}
