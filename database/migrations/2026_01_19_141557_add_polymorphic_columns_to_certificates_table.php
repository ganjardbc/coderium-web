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
        Schema::table('certificates', function (Blueprint $table) {
            // Add polymorphic columns for certifiable entities
            $table->string('certifiable_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('certifiable_id')->nullable()->after('certifiable_type');

            // Add template_id reference for dynamic template selection
            $table->foreignId('template_id')->nullable()->after('certifiable_id')->constrained('certificate_templates')->onDelete('set null');

            // Create indexes for polymorphic relationships
            $table->index(['certifiable_type', 'certifiable_id'], 'idx_certifiable');

            // Add unique constraint for polymorphic relationships (user can have one certificate per certifiable entity)
            $table->unique(['user_id', 'certifiable_type', 'certifiable_id'], 'unique_user_certifiable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_certifiable');
            $table->dropUnique('unique_user_certifiable');

            // Drop foreign key constraint
            $table->dropForeign(['template_id']);

            // Drop columns
            $table->dropColumn(['certifiable_type', 'certifiable_id', 'template_id']);
        });
    }
};
