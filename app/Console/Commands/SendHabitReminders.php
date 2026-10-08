<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\HabitReminder;
use App\Services\HabitMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            $deliveryId = null;
            try {
                $user = $candidate->fresh();
                if (! $user || ! $user->reminders_enabled) {
                    continue;
                }
                if (config('mail.demo_recipient') && strcasecmp($user->email, config('mail.demo_recipient')) !== 0) {
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
                $weekCounts = $metrics->weeklyGoals($user, $today)->mapWithKeys(fn ($goal) => [$goal['habit']->id => $goal['done']])->all();
                $pending = $habits->filter(fn ($habit) => ! in_array($habit->id, $completed, true) &&
                    ($habit->isDueOn($today) || ($habit->schedule_type === 'weekly' && ($weekCounts[$habit->id] ?? 0) < $habit->weekly_target)));

                if ($pending->isEmpty()) {
                    continue;
                }
                // Commit the unique attempt before contacting SMTP. A crash must not
                // cause another process to automatically send the same day's digest.
                if (! DB::table('reminder_deliveries')->insertOrIgnore([
                    'user_id' => $user->id, 'local_date' => $date,
                    'status' => 'sending', 'attempted_at' => now(),
                ])) {
                    continue;
                }
                $deliveryId = DB::table('reminder_deliveries')->where('user_id', $user->id)
                    ->where('local_date', $date)->value('id');
                $user->notify(new HabitReminder($pending->pluck('name')->all(), $date));
                DB::transaction(function () use ($user, $date, $deliveryId) {
                    DB::table('reminder_deliveries')->where('id', $deliveryId)->update(['status' => 'sent', 'sent_at' => now()]);
                    $user->forceFill(['reminder_last_sent_on' => $date])->save();
                });
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                if ($deliveryId) {
                    try {
                        DB::table('reminder_deliveries')->where('id', $deliveryId)->update(['status' => 'uncertain']);
                    } catch (Throwable $recordingFailure) {
                        report($recordingFailure); // The durable 'sending' claim still blocks retries.
                    }
                }
                $failed++;
            } finally {
                $lock->release();
            }
        }
        $this->info("Reminders sent: {$sent}; failed: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
