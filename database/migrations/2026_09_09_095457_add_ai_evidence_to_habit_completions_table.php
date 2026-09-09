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
        Schema::table('habit_completions', function (Blueprint $table) {
    $table->string('photo_path')->nullable()->after('completed_on');
    $table->string('ai_status', 20)->default('pending')->after('photo_path');
    $table->text('ai_reason')->nullable()->after('ai_status');
    $table->json('ai_result')->nullable()->after('ai_reason');
    $table->timestamp('analyzed_at')->nullable()->after('ai_result');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('habit_completions', function (Blueprint $table) {
            //
        });
    }
};
