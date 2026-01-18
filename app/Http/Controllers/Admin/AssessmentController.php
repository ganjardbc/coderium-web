<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssessmentController extends Controller
{
    public function index()
    {
        $assessments = Assessment::with(['assessable', 'questions'])
            ->withCount(['questions'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/classroom/AssessmentIndex', [
            'assessments' => $assessments,
        ]);
    }

    public function create(Request $request)
    {
        $moduleId = $request->get('module_id');

        if (!$moduleId) {
            // Show module selection page
            $modules = Module::with('level.track')->orderBy('id')->get();
            return Inertia::render('admin/classroom/AssessmentCreate', [
                'modules' => $modules,
            ]);
        }

        // Show assessment creation form
        $module = Module::with('level.track')->findOrFail($moduleId);

        return Inertia::render('admin/classroom/AssessmentEditor', [
            'module' => $module,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
            'questions' => 'array',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,true_false,code_output,conceptual',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.order_index' => 'required|integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
            'questions.*.options.*.order_index' => 'required|integer|min:1',
        ]);

        // Create assessment with polymorphic relationship to module
        $assessment = Assessment::create([
            'assessable_type' => 'App\\Models\\Module',
            'assessable_id' => $validated['module_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'time_limit' => $validated['time_limit'],
            'passing_score' => $validated['passing_score'],
            'max_attempts' => $validated['max_attempts'] ?? 3,
            'is_required' => $validated['is_required'] ?? false,
        ]);

        // Handle questions
        if (isset($validated['questions'])) {
            foreach ($validated['questions'] as $questionData) {
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
        }

        return redirect()->route('admin.classroom.assessments.index')
            ->with('success', 'Assessment created successfully.');
    }

    public function edit(Assessment $assessment)
    {
        // Load the assessable relationship and questions with options
        $assessment->load(['assessable', 'questions.options' => function ($query) {
            $query->orderBy('order_index');
        }]);

        // For now, we'll assume it's a module-based assessment
        // In a more complex system, you'd handle different assessable types
        $module = null;
        if ($assessment->assessable_type === 'App\\Models\\Module') {
            $module = Module::with('level.track')->find($assessment->assessable_id);
        }

        return Inertia::render('admin/classroom/AssessmentEditor', [
            'assessment' => $assessment,
            'module' => $module,
        ]);
    }

    public function update(Request $request, Assessment $assessment)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
            'is_required' => 'boolean',
            'questions' => 'array',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|in:multiple_choice,true_false,code_output,conceptual',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.order_index' => 'required|integer|min:1',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.options' => 'required|array|min:2',
            'questions.*.options.*.option_text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
            'questions.*.options.*.order_index' => 'required|integer|min:1',
        ]);

        // Update assessment with polymorphic relationship
        $assessment->update([
            'assessable_type' => 'App\\Models\\Module',
            'assessable_id' => $validated['module_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'time_limit' => $validated['time_limit'],
            'passing_score' => $validated['passing_score'],
            'max_attempts' => $validated['max_attempts'] ?? 3,
            'is_required' => $validated['is_required'] ?? false,
        ]);

        // Handle questions
        if (isset($validated['questions'])) {
            // Delete existing questions and their options
            $assessment->questions()->delete();

            // Create new questions
            foreach ($validated['questions'] as $questionData) {
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
        }

        return redirect()->route('admin.classroom.assessments.index')
            ->with('success', 'Assessment updated successfully.');
    }

    public function destroy(Assessment $assessment)
    {
        $assessment->delete();

        return redirect()->route('admin.classroom.assessments.index')
            ->with('success', 'Assessment deleted successfully.');
    }
}
