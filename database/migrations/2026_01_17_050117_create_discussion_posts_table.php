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
        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('discussion_posts')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_instructor_response')->default(false);
            $table->boolean('is_moderated')->default(false);
            $table->timestamps();

            $table->index(['discussion_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
            $table->index('is_instructor_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussion_posts');
    }
};
