<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HabitMetrics
{
    /**
     * @return Collection<int, \App\Models\Habit>
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
            ->whereIn('habit_id', $habitIds)
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])
            ->get(['completed_on']);

        $counts = [];

        foreach ($rows as $row) {
            $dateKey = $row->completed_on->toDateString();
            $counts[$dateKey] = ($counts[$dateKey] ?? 0) + 1;
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
            ->whereIn('habit_id', $habitIds)
            ->whereBetween('completed_on', [$start->toDateString(), $end->toDateString()])
            ->get(['habit_id']);

        $counts = array_fill_keys($habitIds, 0);

        foreach ($rows as $row) {
            $habitId = (int) $row->habit_id;
            $counts[$habitId] = ($counts[$habitId] ?? 0) + 1;
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

