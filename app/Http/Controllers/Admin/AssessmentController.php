<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Assessment::with(['assessable', 'questions'])
            ->withCount(['questions', 'attempts']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by assessable type
        if ($request->filled('type')) {
            $type = $request->get('type');
            if ($type === 'module') {
                $query->where('assessable_type', 'App\\Models\\Module');
            } elseif ($type === 'standalone') {
                $query->whereNull('assessable_type');
            }
        }

        // Filter by required status
        if ($request->filled('required')) {
            $required = $request->get('required');
            if ($required === 'required') {
                $query->where('is_required', true);
            } elseif ($required === 'optional') {
                $query->where('is_required', false);
            }
        }

        $assessments = $query->orderBy('created_at', 'desc')->paginate(5);

        return Inertia::render('admin/assessments/Index', [
            'assessments' => $assessments,
            'filters' => $request->only(['search', 'type', 'required']),
        ]);
    }

    public function form(Request $request, ?Assessment $assessment = null)
    {
        $modules = Module::orderBy('title')->get();

        if ($assessment) {
            // Edit mode
            $assessment->load([
                'assessable',
                'questions.options' => function ($query) {
                    $query->orderBy('order_index');
                }
            ]);

            return Inertia::render('admin/assessments/Form', [
                'assessment' => new AssessmentResource($assessment),
                'modules' => $modules,
            ]);
        } else {
            // Create mode
            return Inertia::render('admin/assessments/Form', [
                'modules' => $modules,
                'selectedModuleId' => $request->get('module_id'),
            ]);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'module_id' => 'nullable|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,true_false,code_output,conceptual',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
        ]);

        DB::transaction(function () use ($request) {
            // Create assessment
            $assessmentData = [
                'title' => $request->title,
                'description' => $request->description,
                'time_limit' => $request->time_limit,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts ?? 3,
                'is_required' => $request->boolean('is_required'),
            ];

            // Add polymorphic relationship if module is selected
            if ($request->module_id) {
                $assessmentData['assessable_type'] = 'App\\Models\\Module';
                $assessmentData['assessable_id'] = $request->module_id;
            }

            $assessment = Assessment::create($assessmentData);

            // Create questions and options
            foreach ($request->questions as $questionData) {
                $question = $assessment->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'points' => $questionData['points'],
                    'order_index' => $questionData['order_index'],
                    'explanation' => $questionData['explanation'],
                ]);

                // Create question options
                foreach ($questionData['options'] as $optionData) {
                    $question->options()->create([
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'],
                        'order_index' => $optionData['order_index'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.modules.show', $request->module_id)
            ->with('query', ['tab' => 'lessons'])
            ->with('success', 'Assessment created successfully.');
    }

    public function show(Assessment $assessment)
    {
        $assessment->load([
            'assessable',
            'questions.options' => function ($query) {
                $query->orderBy('order_index');
            },
            'attempts.user'
        ])->loadCount(['questions', 'attempts']);

        return Inertia::render('admin/assessments/Show', [
            'assessment' => new AssessmentResource($assessment),
        ]);
    }



    public function update(Request $request, Assessment $assessment)
    {
        $request->validate([
            'module_id' => 'nullable|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,true_false,code_output,conceptual',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
        ]);

        DB::transaction(function () use ($request, $assessment) {
            // Update assessment
            $assessmentData = [
                'title' => $request->title,
                'description' => $request->description,
                'time_limit' => $request->time_limit,
                'passing_score' => $request->passing_score,
                'max_attempts' => $request->max_attempts ?? 3,
                'is_required' => $request->boolean('is_required'),
            ];

            // Update polymorphic relationship
            if ($request->module_id) {
                $assessmentData['assessable_type'] = 'App\\Models\\Module';
                $assessmentData['assessable_id'] = $request->module_id;
            } else {
                $assessmentData['assessable_type'] = null;
                $assessmentData['assessable_id'] = null;
            }

            $assessment->update($assessmentData);

            // Delete existing questions and options
            $assessment->questions()->delete();

            // Create new questions and options
            foreach ($request->questions as $questionData) {
                $question = $assessment->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'question_type' => $questionData['question_type'],
                    'points' => $questionData['points'],
                    'order_index' => $questionData['order_index'],
                    'explanation' => $questionData['explanation'],
                ]);

                // Create question options
                foreach ($questionData['options'] as $optionData) {
                    $question->options()->create([
                        'option_text' => $optionData['option_text'],
                        'is_correct' => $optionData['is_correct'],
                        'order_index' => $optionData['order_index'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.modules.show', $request->module_id)
            ->with('query', ['tab' => 'lessons'])
            ->with('success', 'Assessment updated successfully.');
    }

    public function destroy(Assessment $assessment)
    {
        DB::transaction(function () use ($assessment) {
            // Delete questions and options (cascade should handle this)
            $assessment->questions()->delete();

            // Delete the assessment
            $assessment->delete();
        });

        return redirect()
            ->route('admin.modules.show', $request->module_id)
            ->with('query', ['tab' => 'lessons'])
            ->with('success', 'Assessment deleted successfully.');
    }
}
