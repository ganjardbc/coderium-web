<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ContentService
{
    /**
     * Create a new level.
     *
     * @param array $data
     * @return Level
     * @throws ValidationException
     */
    public function createLevel(array $data): Level
    {
        $this->validateLevelData($data);

        return DB::transaction(function () use ($data) {
            // Set order_index if not provided
            if (!isset($data['order_index'])) {
                $data['order_index'] = $this->getNextOrderIndex('levels', 'track_id', $data['track_id']);
            }

            return Level::create($data);
        });
    }

    /**
     * Create a new module.
     *
     * @param array $data
     * @return Module
     * @throws ValidationException
     */
    public function createModule(array $data): Module
    {
        $this->validateModuleData($data);

        return DB::transaction(function () use ($data) {
            // Set order_index if not provided
            if (!isset($data['order_index'])) {
                $data['order_index'] = $this->getNextOrderIndex('modules', 'level_id', $data['level_id']);
            }

            return Module::create($data);
        });
    }

    /**
     * Create a new lesson.
     *
     * @param array $data
     * @return Lesson
     * @throws ValidationException
     */
    public function createLesson(array $data): Lesson
    {
        $this->validateLessonData($data);

        return DB::transaction(function () use ($data) {
            // Process rich text content
            if (isset($data['content'])) {
                $data['content'] = $this->processRichTextContent($data['content']);
            }

            // Set order_index if not provided
            if (!isset($data['order_index'])) {
                $data['order_index'] = $this->getNextOrderIndex('lessons', 'module_id', $data['module_id']);
            }

            return Lesson::create($data);
        });
    }

    /**
     * Update a level.
     *
     * @param Level $level
     * @param array $data
     * @return Level
     * @throws ValidationException
     */
    public function updateLevel(Level $level, array $data): Level
    {
        $this->validateLevelData($data, $level->id);

        return DB::transaction(function () use ($level, $data) {
            $level->update($data);
            return $level->fresh();
        });
    }

    /**
     * Update a module.
     *
     * @param Module $module
     * @param array $data
     * @return Module
     * @throws ValidationException
     */
    public function updateModule(Module $module, array $data): Module
    {
        $this->validateModuleData($data, $module->id);

        return DB::transaction(function () use ($module, $data) {
            $module->update($data);
            return $module->fresh();
        });
    }

    /**
     * Update a lesson.
     *
     * @param Lesson $lesson
     * @param array $data
     * @return Lesson
     * @throws ValidationException
     */
    public function updateLesson(Lesson $lesson, array $data): Lesson
    {
        $this->validateLessonData($data, $lesson->id);

        return DB::transaction(function () use ($lesson, $data) {
            // Process rich text content
            if (isset($data['content'])) {
                $data['content'] = $this->processRichTextContent($data['content']);
            }

            $lesson->update($data);
            return $lesson->fresh();
        });
    }

    /**
     * Delete content with progress preservation.
     *
     * @param string $type
     * @param int $id
     * @return bool
     * @throws ValidationException
     */
    public function deleteContentWithProgressPreservation(string $type, int $id): bool
    {
        return DB::transaction(function () use ($type, $id) {
            switch ($type) {
                case 'level':
                    return $this->deleteLevel($id);
                case 'module':
                    return $this->deleteModule($id);
                case 'lesson':
                    return $this->deleteLesson($id);
                default:
                    throw new \InvalidArgumentException("Invalid content type: {$type}");
            }
        });
    }

    /**
     * Reorder content items.
     *
     * @param string $type
     * @param array $orderData
     * @return bool
     */
    public function reorderContent(string $type, array $orderData): bool
    {
        return DB::transaction(function () use ($type, $orderData) {
            foreach ($orderData as $item) {
                $model = $this->getModelClass($type);
                $model::where('id', $item['id'])
                    ->update(['order_index' => $item['order_index']]);
            }
            return true;
        });
    }

    /**
     * Get content hierarchy for a track.
     *
     * @param int $trackId
     * @param bool $publishedOnly
     * @return Collection
     */
    public function getContentHierarchy(int $trackId, bool $publishedOnly = true): Collection
    {
        $query = Level::where('track_id', $trackId)
            ->with(['modules.lessons']);

        if ($publishedOnly) {
            $query->where('is_published', true)
                ->whereHas('modules', function ($moduleQuery) {
                    $moduleQuery->where('is_published', true)
                        ->whereHas('lessons', function ($lessonQuery) {
                            $lessonQuery->where('is_published', true);
                        });
                });
        }

        return $query->orderBy('order_index')->get();
    }

    /**
     * Validate level data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateLevelData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'track_id' => 'required|exists:tracks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'difficulty' => 'required|in:beginner,intermediate,advanced',
            'order_index' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate difficulty progression within track
        if (isset($data['track_id']) && isset($data['difficulty'])) {
            $this->validateDifficultyProgression($data['track_id'], $data['difficulty'], $excludeId);
        }
    }

    /**
     * Validate module data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateModuleData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_index' => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1|max:600', // Max 10 hours per module
            'is_published' => 'boolean',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate lesson data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateLessonData(array $data, ?int $excludeId = null): void
    {
        $rules = [
            'module_id' => 'required|exists:modules,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string|max:65535', // TEXT field limit
            'order_index' => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:1|max:120', // Max 2 hours per lesson
            'is_published' => 'boolean',
            'lesson_type' => 'nullable|in:text,video,interactive',
        ];

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate content length for different lesson types
        if (isset($data['content']) && isset($data['lesson_type'])) {
            $this->validateContentByType($data['content'], $data['lesson_type']);
        }
    }

    /**
     * Process rich text content.
     *
     * @param string $content
     * @return string
     */
    private function processRichTextContent(string $content): string
    {
        // Sanitize HTML content
        $content = $this->sanitizeHtml($content);

        // Process code snippets for syntax highlighting
        $content = $this->processCodeSnippets($content);

        // Process markdown-style formatting
        $content = $this->processMarkdownFormatting($content);

        return $content;
    }

    /**
     * Sanitize HTML content.
     *
     * @param string $content
     * @return string
     */
    private function sanitizeHtml(string $content): string
    {
        // Allow specific HTML tags for rich text
        $allowedTags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><code><pre><blockquote>';

        return strip_tags($content, $allowedTags);
    }

    /**
     * Process code snippets for syntax highlighting.
     *
     * @param string $content
     * @return string
     */
    private function processCodeSnippets(string $content): string
    {
        // Add data attributes for syntax highlighting
        $content = preg_replace(
            '/<code\s+class="language-([^"]+)">/i',
            '<code class="language-$1" data-lang="$1">',
            $content
        );

        // Wrap inline code snippets
        $content = preg_replace(
            '/`([^`]+)`/',
            '<code class="inline-code">$1</code>',
            $content
        );

        return $content;
    }

    /**
     * Process markdown-style formatting.
     *
     * @param string $content
     * @return string
     */
    private function processMarkdownFormatting(string $content): string
    {
        // Convert **bold** to <strong>
        $content = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $content);

        // Convert *italic* to <em>
        $content = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $content);

        // Convert [link](url) to <a>
        $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2" target="_blank">$1</a>', $content);

        return $content;
    }

    /**
     * Delete a level with progress preservation.
     *
     * @param int $levelId
     * @return bool
     */
    private function deleteLevel(int $levelId): bool
    {
        $level = Level::findOrFail($levelId);

        // Check if there's any progress data
        $hasProgress = $this->hasProgressData('level', $levelId);

        if ($hasProgress) {
            // Soft delete to preserve progress
            return $level->delete();
        } else {
            // Hard delete if no progress exists
            return $level->forceDelete();
        }
    }

    /**
     * Delete a module with progress preservation.
     *
     * @param int $moduleId
     * @return bool
     */
    private function deleteModule(int $moduleId): bool
    {
        $module = Module::findOrFail($moduleId);

        // Check if there's any progress data
        $hasProgress = $this->hasProgressData('module', $moduleId);

        if ($hasProgress) {
            // Soft delete to preserve progress
            return $module->delete();
        } else {
            // Hard delete if no progress exists
            return $module->forceDelete();
        }
    }

    /**
     * Delete a lesson with progress preservation.
     *
     * @param int $lessonId
     * @return bool
     */
    private function deleteLesson(int $lessonId): bool
    {
        $lesson = Lesson::findOrFail($lessonId);

        // Check if there's any progress data
        $hasProgress = LessonProgress::where('lesson_id', $lessonId)->exists();

        if ($hasProgress) {
            // Soft delete to preserve progress
            return $lesson->delete();
        } else {
            // Hard delete if no progress exists
            return $lesson->forceDelete();
        }
    }

    /**
     * Check if progress data exists for content.
     *
     * @param string $type
     * @param int $id
     * @return bool
     */
    private function hasProgressData(string $type, int $id): bool
    {
        switch ($type) {
            case 'level':
                return DB::table('lesson_progress')
                    ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
                    ->join('modules', 'lessons.module_id', '=', 'modules.id')
                    ->where('modules.level_id', $id)
                    ->exists();

            case 'module':
                return DB::table('lesson_progress')
                    ->join('lessons', 'lesson_progress.lesson_id', '=', 'lessons.id')
                    ->where('lessons.module_id', $id)
                    ->exists();

            default:
                return false;
        }
    }

    /**
     * Get next order index for content.
     *
     * @param string $table
     * @param string $parentColumn
     * @param int $parentId
     * @return int
     */
    private function getNextOrderIndex(string $table, string $parentColumn, int $parentId): int
    {
        $maxOrder = DB::table($table)
            ->where($parentColumn, $parentId)
            ->max('order_index');

        return ($maxOrder ?? -1) + 1;
    }

    /**
     * Validate difficulty progression within track.
     *
     * @param int $trackId
     * @param string $difficulty
     * @param int|null $excludeId
     * @throws ValidationException
     */
    private function validateDifficultyProgression(int $trackId, string $difficulty, ?int $excludeId = null): void
    {
        $difficultyOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
        $currentOrder = $difficultyOrder[$difficulty];

        // Check if there are existing levels with higher difficulty
        $query = Level::where('track_id', $trackId)
            ->whereIn('difficulty', array_keys($difficultyOrder));

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $existingLevels = $query->get();

        foreach ($existingLevels as $level) {
            $existingOrder = $difficultyOrder[$level->difficulty];

            // Ensure proper progression (no gaps in difficulty)
            if ($currentOrder > $existingOrder + 1) {
                throw ValidationException::withMessages([
                    'difficulty' => "Cannot create {$difficulty} level without intermediate levels.",
                ]);
            }
        }
    }

    /**
     * Validate content by lesson type.
     *
     * @param string $content
     * @param string $lessonType
     * @throws ValidationException
     */
    private function validateContentByType(string $content, string $lessonType): void
    {
        switch ($lessonType) {
            case 'video':
                // Video lessons should have minimal text content
                if (strlen(strip_tags($content)) > 1000) {
                    throw ValidationException::withMessages([
                        'content' => 'Video lessons should have concise text content (max 1000 characters).',
                    ]);
                }
                break;

            case 'interactive':
                // Interactive lessons should have substantial content
                if (strlen(strip_tags($content)) < 100) {
                    throw ValidationException::withMessages([
                        'content' => 'Interactive lessons should have substantial content (min 100 characters).',
                    ]);
                }
                break;
        }
    }

    /**
     * Get model class for content type.
     *
     * @param string $type
     * @return string
     */
    private function getModelClass(string $type): string
    {
        return match ($type) {
            'level' => Level::class,
            'module' => Module::class,
            'lesson' => Lesson::class,
            default => throw new \InvalidArgumentException("Invalid content type: {$type}"),
        };
    }
}
