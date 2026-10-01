<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HabitMetrics
{
    public function schedulePerformance(User $user, Collection $habits, Carbon $start, Carbon $end): array
    {
        $counts = [];
        foreach ($habits as $habit) {
            if ($habit->schedule_type === 'weekly') {
                continue;
            }
            $target = 0;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($habit->isDueOn($date)) {
                    $target++;
                }
            }
            if ($target) {
                $counts[$habit->id] = ['habit' => $habit, 'done' => 0, 'target' => $target];
            }
        }
        $rows = $user->habitCompletions()->where('ai_status', 'approved')->whereIn('habit_id', array_keys($counts))
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])->toBase()->get(['habit_id', 'completed_on']);
        foreach ($rows as $row) {
            if ($counts[$row->habit_id]['habit']->isDueOn(Carbon::parse($row->completed_on))) {
                $counts[$row->habit_id]['done']++;
            }
        }

        return $counts;
    }

    public function scheduledDays(User $user, Collection $habits, Carbon $start, Carbon $end): array
    {
        $rows = $user->habitCompletions()->where('ai_status', 'approved')
            ->whereIn('habit_id', $habits->pluck('id'))
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])
            ->toBase()->get(['habit_id', 'completed_on']);
        $completed = [];
        foreach ($rows as $row) {
            $completed[$row->completed_on][$row->habit_id] = true;
        }
        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $due = $habits->filter(fn ($habit) => $habit->isDueOn($date));
            $done = $due->filter(fn ($habit) => isset($completed[$date->toDateString()][$habit->id]))->count();
            $total = $due->count();
            $days[$date->toDateString()] = ['date' => $date->copy(), 'label' => $date->format('D'), 'done' => $done,
                'total' => $total, 'percent' => $total ? (int) round($done / $total * 100) : 0, 'perfect' => $total > 0 && $done === $total];
        }

        return $days;
    }

    public function scheduledStreaks(array $days): array
    {
        $current = $best = 0;
        foreach ($days as $day) {
            if ($day['total'] === 0) {
                continue;
            } // Rest days neither add to nor break a streak.
            $current = $day['perfect'] ? $current + 1 : 0;
            $best = max($best, $current);
        }

        return ['current' => $current, 'best' => $best];
    }

    public function weeklyGoals(User $user, Carbon $today): Collection
    {
        $habits = $this->dailyHabits($user)->where('schedule_type', 'weekly');
        $counts = $this->completionCountsByHabit($user, $habits->pluck('id')->all(), $today->copy()->startOfWeek(Carbon::MONDAY), $today);

        return $habits->map(fn ($habit) => ['habit' => $habit, 'done' => $counts[$habit->id] ?? 0, 'target' => $habit->weekly_target]);
    }

    /**
     * @return Collection<int, Habit>
     */
    public function dailyHabits(User $user): Collection
    {
        return $user->habits()
            ->where('is_daily', true)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array<int, int>  $habitIds
     * @return array<int, int>
     */
    public function completedHabitIdsForDate(User $user, array $habitIds, Carbon $date): array
    {
        if ($habitIds === []) {
            return [];
        }

        return $user->habitCompletions()
            ->where('ai_status', 'approved')
            ->whereIn('habit_id', $habitIds)
            ->where('completed_on', $date->toDateString())
            ->pluck('habit_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $habitIds
     * @return array<string, int>
     */
    public function completionCountsByDate(User $user, array $habitIds, Carbon $start, Carbon $end): array
    {
        if ($habitIds === []) {
            return [];
        }

        $rows = $user->habitCompletions()
            ->where('ai_status', 'approved')
            ->whereIn('habit_id', $habitIds)
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('completed_on, COUNT(*) as completion_count')
            ->groupBy('completed_on')
            ->toBase()
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->completed_on] = (int) $row->completion_count;
        }

        return $counts;
    }

    /**
     * @param  array<int, int>  $habitIds
     * @return array<int, int>
     */
    public function completionCountsByHabit(User $user, array $habitIds, Carbon $start, Carbon $end): array
    {
        if ($habitIds === []) {
            return [];
        }

        $rows = $user->habitCompletions()
            ->where('ai_status', 'approved')
            ->whereIn('habit_id', $habitIds)
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('habit_id, COUNT(*) as completion_count')
            ->groupBy('habit_id')
            ->toBase()
            ->get();

        $counts = array_fill_keys($habitIds, 0);

        foreach ($rows as $row) {
            $habitId = (int) $row->habit_id;
            $counts[$habitId] = (int) $row->completion_count;
        }

        return $counts;
    }

    public function perfectStreak(array $countsByDate, int $totalHabits, Carbon $start, Carbon $end): int
    {
        if ($totalHabits === 0) {
            return 0;
        }

        $streak = 0;

        for ($date = $end->copy(); $date->greaterThanOrEqualTo($start); $date->subDay()) {
            $key = $date->toDateString();
            $count = $countsByDate[$key] ?? 0;

            if ($count >= $totalHabits) {
                $streak++;

                continue;
            }

            break;
        }

        return $streak;
    }

    public function bestPerfectStreak(array $countsByDate, int $totalHabits, Carbon $start, Carbon $end): int
    {
        if ($totalHabits === 0) {
            return 0;
        }

        $best = 0;
        $current = 0;

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $key = $date->toDateString();
            $count = $countsByDate[$key] ?? 0;

            if ($count >= $totalHabits) {
                $current++;
                $best = max($best, $current);

                continue;
            }

            $current = 0;
        }

        return $best;
    }
}
