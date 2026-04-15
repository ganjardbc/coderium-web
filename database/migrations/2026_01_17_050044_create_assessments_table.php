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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessable_type'); // lesson or module
            $table->unsignedBigInteger('assessable_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('passing_score', 5, 2)->default(70.00);
            $table->integer('max_attempts')->default(3);
            $table->integer('time_limit')->nullable()->comment('Time limit in minutes');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['assessable_type', 'assessable_id'], 'assessable_index');
            $table->index('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
