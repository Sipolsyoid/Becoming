<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('habit_completions', function (Blueprint $table) {
            $table->string('verification_habit_name', 80)->nullable();
            $table->string('photo_habit_name', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('habit_completions', fn (Blueprint $table) => $table->dropColumn(['verification_habit_name', 'photo_habit_name']));
    }
};
