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
            $table->integer('download_count')->default(0)->after('verification_url');
            $table->timestamp('downloaded_at')->nullable()->after('download_count');
            $table->boolean('is_valid')->default(true)->after('downloaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn(['download_count', 'downloaded_at', 'is_valid']);
        });
    }
};
