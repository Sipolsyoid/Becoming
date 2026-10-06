<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View
    {
        $user = $request->user();
        $today = $user->localToday();

        $activeHabits = $metrics->dailyHabits($user);
        $dailyHabits = $activeHabits->filter(fn ($habit) => $habit->isDueOn($today));
        $habitIds = $dailyHabits->pluck('id')->map(fn ($id) => (int) $id)->all();

        $completedHabitIds = $metrics->completedHabitIdsForDate($user, $habitIds, $today);

        $completedCount = count($completedHabitIds);
        $totalCount = $dailyHabits->count();
        $progressPercent = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

        $rangeStart = $today->copy()->subDays(364);
        $days = $metrics->scheduledDays($user, $activeHabits, $rangeStart, $today);
        $streak = $metrics->scheduledStreaks($days)['current'];

        $weekStart = $today->copy()->subDays(6);
        $focusHabit = null;
        $focusHabitCount = null;
        $focusHabitTarget = null;
        $lowestRate = INF;
        foreach ($metrics->schedulePerformance($user, $dailyHabits, $weekStart, $today) as $result) {
            $rate = $result['done'] / $result['target'];
            if ($rate < $lowestRate) {
                $lowestRate = $rate;
                $focusHabit = $result['habit'];
                $focusHabitCount = $result['done'];
                $focusHabitTarget = $result['target'];
            }
        }

        return view('dashboard', [
            'today' => $today,
            'weeklyGoals' => $metrics->weeklyGoals($user, $today),
            'weeklyCompletedToday' => $metrics->completedHabitIdsForDate($user, $activeHabits->where('schedule_type', 'weekly')->pluck('id')->all(), $today),
            'dailyHabits' => $dailyHabits,
            'completedHabitIds' => $completedHabitIds,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'progressPercent' => $progressPercent,
            'streak' => $streak,
            'focusHabit' => $focusHabit,
            'focusHabitCount' => $focusHabitCount,
            'focusHabitTarget' => $focusHabitTarget,
        ]);
    }
}
