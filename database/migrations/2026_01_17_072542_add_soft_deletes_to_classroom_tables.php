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
        // Add soft deletes to tracks table
        Schema::table('tracks', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to levels table
        Schema::table('levels', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to modules table
        Schema::table('modules', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to lessons table
        Schema::table('lessons', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to assessments table
        Schema::table('assessments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
