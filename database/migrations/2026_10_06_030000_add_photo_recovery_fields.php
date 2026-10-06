<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_completions', function (Blueprint $table) {
            $table->timestamp('verification_started_at')->nullable();
            $table->unsignedTinyInteger('verification_recoveries')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('habit_completions', fn (Blueprint $table) => $table->dropColumn(['verification_started_at', 'verification_recoveries']));
    }
};
