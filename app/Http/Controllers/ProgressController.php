<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $dailyHabits = $metrics->dailyHabits($user);
        $habitIds = $dailyHabits->where('schedule_type', '!=', 'weekly')->pluck('id')->all();
        $totalCount = count($habitIds);
        $weekStart = $today->copy()->subDays(6);
        $rangeStart = $today->copy()->subDays(364);
        $days = $metrics->scheduledDays($user, $dailyHabits, $rangeStart, $today);
        $chartDays = array_values(array_slice($days, -7, null, true));
        $weeklyDone = array_sum(array_column($chartDays, 'done'));
        $weeklyTotal = array_sum(array_column($chartDays, 'total'));
        $weeklyPercent = $weeklyTotal ? (int) round($weeklyDone / $weeklyTotal * 100) : 0;
        $bestStreak = $metrics->scheduledStreaks($days)['best'];

        $weakHabit = null;
        $weakHabitCount = null;
        $weakHabitTarget = null;
        $lowestRate = INF;
        foreach ($metrics->schedulePerformance($user, $dailyHabits, $weekStart, $today) as $result) {
            $rate = $result['done'] / $result['target'];
            if ($rate < $lowestRate) {
                $lowestRate = $rate;
                $weakHabit = $result['habit'];
                $weakHabitCount = $result['done'];
                $weakHabitTarget = $result['target'];
            }
        }

        return view('progress', [
            'today' => $today,
            'weeklyGoals' => $metrics->weeklyGoals($user, $today),
            'weeklyPercent' => $weeklyPercent,
            'chartDays' => $chartDays,
            'bestStreak' => $bestStreak,
            'weakHabit' => $weakHabit,
            'weakHabitCount' => $weakHabitCount,
            'weakHabitTarget' => $weakHabitTarget,
        ]);
    }
}
