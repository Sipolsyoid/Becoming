<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $dailyHabits = $metrics->dailyHabits($user);
        $habitIds = $dailyHabits->pluck('id')->map(fn ($id) => (int) $id)->all();

        $completedHabitIds = $metrics->completedHabitIdsForDate($user, $habitIds, $today);

        $completedCount = count($completedHabitIds);
        $totalCount = $dailyHabits->count();
        $progressPercent = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

        $rangeStart = $today->copy()->subDays(364);
        $countsByDate = $metrics->completionCountsByDate($user, $habitIds, $rangeStart, $today);
        $streak = $metrics->perfectStreak($countsByDate, $totalCount, $rangeStart, $today);

        $weekStart = $today->copy()->subDays(6);
        $countsByHabitWeek = $metrics->completionCountsByHabit($user, $habitIds, $weekStart, $today);

        $focusHabit = null;
        $focusHabitCount = null;

        if ($totalCount > 0 && $countsByHabitWeek !== []) {
            $lowest = PHP_INT_MAX;
            $focusHabitId = null;

            foreach ($countsByHabitWeek as $habitId => $count) {
                if ($count < $lowest) {
                    $lowest = $count;
                    $focusHabitId = $habitId;
                }
            }

            $focusHabit = $dailyHabits->firstWhere('id', $focusHabitId);
            $focusHabitCount = $lowest;
        }

        return view('dashboard', [
            'today' => $today,
            'dailyHabits' => $dailyHabits,
            'completedHabitIds' => $completedHabitIds,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'progressPercent' => $progressPercent,
            'streak' => $streak,
            'focusHabit' => $focusHabit,
            'focusHabitCount' => $focusHabitCount,
        ]);
    }
}

