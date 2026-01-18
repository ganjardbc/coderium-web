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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->integer('order_index')->default(0);
            $table->integer('estimated_duration')->nullable()->comment('Duration in minutes');
            $table->boolean('is_published')->default(false);
            $table->enum('lesson_type', ['text', 'video', 'interactive'])->default('text');
            $table->timestamps();

            $table->index(['module_id', 'order_index']);
            $table->index(['module_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
