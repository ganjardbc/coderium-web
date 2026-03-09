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
        // Make level_id nullable in modules table for flexible module assignments
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropIndex(['level_id', 'order_index']);
            $table->dropIndex(['level_id', 'is_published']);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->change()->constrained('levels')->onDelete('set null');
            $table->index(['level_id', 'order_index']);
            $table->index(['level_id', 'is_published']);
        });

        // Add missing columns to certificate_templates table
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->string('template_type')->default('track')->after('description');
            $table->json('template_data')->nullable()->after('template_type');
            $table->boolean('is_active')->default(true)->after('is_default');
        });

        // Make track_id nullable in certificates table for polymorphic support
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['track_id']);
            $table->dropUnique(['user_id', 'track_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->change()->constrained('tracks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert certificate_templates changes
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn(['template_type', 'template_data', 'is_active']);
        });

        // Revert certificates table changes
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['track_id']);
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('track_id')->change()->constrained('tracks')->onDelete('cascade');
            $table->unique(['user_id', 'track_id']);
        });

        // Revert modules table changes
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropIndex(['level_id', 'order_index']);
            $table->dropIndex(['level_id', 'is_published']);
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('level_id')->change()->constrained('levels')->onDelete('cascade');
            $table->index(['level_id', 'order_index']);
            $table->index(['level_id', 'is_published']);
        });
    }
};
