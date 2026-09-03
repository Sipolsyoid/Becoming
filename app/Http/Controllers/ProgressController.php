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
        $habitIds = $dailyHabits->pluck('id')->map(fn ($id) => (int) $id)->all();
        $totalCount = $dailyHabits->count();

        $weekStart = $today->copy()->subDays(6);
        $countsByDateWeek = $metrics->completionCountsByDate($user, $habitIds, $weekStart, $today);
        $weeklyDone = array_sum($countsByDateWeek);
        $weeklyTotal = $totalCount * 7;
        $weeklyPercent = $weeklyTotal > 0 ? (int) round(($weeklyDone / $weeklyTotal) * 100) : 0;

        $chartDays = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $dateKey = $date->toDateString();
            $done = $countsByDateWeek[$dateKey] ?? 0;
            $percent = $totalCount > 0 ? (int) round(($done / $totalCount) * 100) : 0;

            $chartDays[] = [
                'date' => $date,
                'label' => $date->format('D'),
                'done' => $done,
                'total' => $totalCount,
                'percent' => $percent,
            ];
        }

        $rangeStart = $today->copy()->subDays(364);
        $countsByDateYear = $metrics->completionCountsByDate($user, $habitIds, $rangeStart, $today);
        $bestStreak = $metrics->bestPerfectStreak($countsByDateYear, $totalCount, $rangeStart, $today);

        $countsByHabitWeek = $metrics->completionCountsByHabit($user, $habitIds, $weekStart, $today);
        $weakHabit = null;
        $weakHabitCount = null;

        if ($totalCount > 0 && $countsByHabitWeek !== []) {
            $lowest = PHP_INT_MAX;
            $weakHabitId = null;

            foreach ($countsByHabitWeek as $habitId => $count) {
                if ($count < $lowest) {
                    $lowest = $count;
                    $weakHabitId = $habitId;
                }
            }

            $weakHabit = $dailyHabits->firstWhere('id', $weakHabitId);
            $weakHabitCount = $lowest;
        }

        return view('progress', [
            'today' => $today,
            'weeklyPercent' => $weeklyPercent,
            'chartDays' => $chartDays,
            'bestStreak' => $bestStreak,
            'weakHabit' => $weakHabit,
            'weakHabitCount' => $weakHabitCount,
        ]);
    }
}

