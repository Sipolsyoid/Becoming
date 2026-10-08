<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('habit_completions')->join('habits', 'habits.id', '=', 'habit_completions.habit_id')
            ->whereColumn('habit_completions.user_id', '!=', 'habits.user_id')->exists()) {
            throw new RuntimeException('Check-in ownership is inconsistent. Resolve these records before migrating; no data has been changed.');
        }
        Schema::table('habits', fn (Blueprint $table) => $table->unique(['id', 'user_id'], 'habits_owner_unique'));
        Schema::table('habit_completions', function (Blueprint $table) {
            $table->foreign(['habit_id', 'user_id'], 'completions_owner_foreign')->references(['id', 'user_id'])->on('habits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('habit_completions', fn (Blueprint $table) => $table->dropForeign('completions_owner_foreign'));
        Schema::table('habits', fn (Blueprint $table) => $table->dropUnique('habits_owner_unique'));
    }
};
