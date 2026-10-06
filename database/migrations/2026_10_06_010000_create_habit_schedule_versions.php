<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('effective_on');
            $table->boolean('is_active');
            $table->string('schedule_type', 16);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('weekly_target')->nullable();
            $table->timestamps();
            $table->unique(['habit_id', 'effective_on']);
        });

        // Older schedule changes were not recorded. Preserve the known schedule as a baseline.
        DB::table('habits')->join('users', 'users.id', '=', 'habits.user_id')
            ->select('habits.*', 'users.timezone')->orderBy('habits.id')->chunkById(200, function ($habits) {
                foreach ($habits as $habit) {
                    DB::table('habit_schedule_versions')->insert([
                        'habit_id' => $habit->id,
                        'effective_on' => Carbon::parse($habit->created_at, 'UTC')->setTimezone($habit->timezone)->toDateString(),
                        'is_active' => $habit->is_daily, 'schedule_type' => $habit->schedule_type,
                        'weekdays' => $habit->weekdays, 'weekly_target' => $habit->weekly_target,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }, 'habits.id', 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_schedule_versions');
    }
};
