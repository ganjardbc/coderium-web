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
        // Add soft deletes to discussions table
        Schema::table('discussions', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes and moderation_reason to discussion_posts table
        Schema::table('discussion_posts', function (Blueprint $table) {
            $table->softDeletes();
            $table->text('moderation_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discussions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('discussion_posts', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('moderation_reason');
        });
    }
};
