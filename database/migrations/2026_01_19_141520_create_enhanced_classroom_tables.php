<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create courses table
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('certificate_template_id')->nullable()->constrained('certificate_templates')->onDelete('set null');
            $table->integer('estimated_duration')->nullable()->comment('Duration in minutes');
            $table->timestamps();

            $table->index(['is_active']);
            $table->index('slug');
        });

        // Create course_modules pivot table
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'module_id'], 'unique_course_module');
            $table->index(['course_id', 'order']);
        });

        // Create level_modules pivot table for flexible module assignments
        Schema::create('level_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('levels')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['level_id', 'module_id'], 'unique_level_module');
            $table->index(['level_id', 'order']);
        });

        // Create course_enrollments table matching track enrollment patterns
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['user_id', 'course_id'], 'unique_user_course');
            $table->index('user_id');
            $table->index('course_id');
            $table->index('progress_percentage');
        });

        // Create learning_progress table with polymorphic relationships
        Schema::create('learning_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('progressable_type');
            $table->unsignedBigInteger('progressable_id');
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->integer('time_spent_minutes')->default(0);
            $table->decimal('engagement_score', 3, 2)->nullable()->comment('Score from 0.00 to 1.00');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['progressable_type', 'progressable_id'], 'idx_progressable');
            $table->unique(['user_id', 'progressable_type', 'progressable_id'], 'unique_user_progressable');
            $table->index('user_id');
            $table->index('completion_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_progress');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('level_modules');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
    }
};
