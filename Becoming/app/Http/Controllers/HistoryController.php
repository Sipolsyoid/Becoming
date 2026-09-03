<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $dailyHabits = $metrics->dailyHabits($user);
        $habitIds = $dailyHabits->pluck('id')->map(fn ($id) => (int) $id)->all();
        $totalCount = $dailyHabits->count();

        $start = $today->copy()->subDays(29);
        $countsByDate = $metrics->completionCountsByDate($user, $habitIds, $start, $today);

        $days = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $today->copy()->subDays($i);
            $key = $date->toDateString();
            $done = $countsByDate[$key] ?? 0;

            $days[] = [
                'date' => $date,
                'done' => $done,
                'total' => $totalCount,
                'perfect' => $totalCount > 0 && $done >= $totalCount,
            ];
        }

        return view('history', [
            'days' => $days,
        ]);
    }
}

