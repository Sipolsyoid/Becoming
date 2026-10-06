<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HabitReminder;
use App\Services\HabitMetrics;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SendHabitReminders extends Command
{
    protected $signature = 'habits:send-reminders';

    protected $description = 'Send one local-day email reminder to users with outstanding habits';

    public function handle(HabitMetrics $metrics): int
    {
        $sent = $failed = 0;
        foreach (User::where('reminders_enabled', true)->lazyById(100) as $candidate) {
            // Protect manual and scheduled runs using the same shared cache lock.
            $lock = Cache::lock('habit-reminder:'.$candidate->id, 300);
            if (! $lock->get()) {
                continue;
            }
            try {
                $user = $candidate->fresh();
                if (! $user || ! $user->reminders_enabled) {
                    continue;
                }
                $now = $user->localNow();
                $date = $now->toDateString();
                if ($now->format('H:i') < $user->reminder_time ||
                    ($user->reminder_last_sent_on && $user->reminder_last_sent_on->toDateString() >= $date)) {
                    continue;
                }
                $today = $now->copy()->startOfDay();
                $habits = $metrics->dailyHabits($user);
                $completed = $metrics->completedHabitIdsForDate($user, $habits->pluck('id')->all(), $today);
                $weekCounts = $metrics->completionCountsByHabit($user, $habits->where('schedule_type', 'weekly')->pluck('id')->all(), $today->copy()->startOfWeek(CarbonInterface::MONDAY), $today);
                $pending = $habits->filter(fn ($habit) => ! in_array($habit->id, $completed, true) &&
                    ($habit->isDueOn($today) || ($habit->schedule_type === 'weekly' && ($weekCounts[$habit->id] ?? 0) < $habit->weekly_target)));

                if ($pending->isEmpty()) {
                    continue;
                }
                $user->notify(new HabitReminder($pending->pluck('name')->all(), $date));
                $user->forceFill(['reminder_last_sent_on' => $date])->save();
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            } finally {
                $lock->release();
            }
        }
        $this->info("Reminders sent: {$sent}; failed: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
