<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View
    {
        $user = $request->user();
        $today = $user->localToday();

        $dailyHabits = $metrics->dailyHabits($user);
        $days = array_reverse(array_values($metrics->scheduledDays($user, $metrics->historyHabits($user), $today->copy()->subDays(29), $today)));

        return view('history', [
            'days' => $days,
            'weeklyHistory' => $metrics->weeklyHistory($user, $today),
        ]);
    }
}
