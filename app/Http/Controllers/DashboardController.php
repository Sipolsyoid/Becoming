<?php

namespace App\Http\Controllers;

use App\Services\HabitMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, HabitMetrics $metrics): View|JsonResponse
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
        $days = $metrics->scheduledDays($user, $metrics->historyHabits($user), $rangeStart, $today);
        $streak = $metrics->scheduledStreaks($days, $today)['current'];

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

        $weeklyGoals = $metrics->weeklyGoals($user, $today);
        $liveProgress = [
            'completed' => $completedCount, 'total' => $totalCount, 'percent' => $progressPercent,
            'streak' => $streak, 'ids' => $completedHabitIds,
            'focusName' => $focusHabit?->name ?? 'Find your rhythm',
            'focusText' => $focusHabit ? "Completed {$focusHabitCount} of {$focusHabitTarget} scheduled days in the last 7 days. Make a little space for it today." : 'Your least-completed habit scheduled today will appear here once you add a habit.',
            'weekly' => $weeklyGoals->mapWithKeys(fn ($goal) => [$goal['habit']->id => $goal['done']])->all(),
        ];
        if ($request->expectsJson()) {
            return response()->json($liveProgress)->header('Cache-Control', 'no-store');
        }

        return view('dashboard', [
            'liveProgress' => $liveProgress,
            'photoChecks' => $user->habitCompletions()->where('completed_on', $today->toDateString())->get()->keyBy('habit_id'),
            'earlierChecks' => $user->habitCompletions()->with('habit')->where('completed_on', '!=', $today->toDateString())
                ->whereIn('verification_status', ['queued', 'checking', 'failed'])->latest('verification_requested_at')->limit(10)->get(),
            'recentChecks' => $user->habitCompletions()->with('habit')->whereIn('verification_status', ['approved', 'rejected', 'needs_review'])
                ->where('analyzed_at', '>=', now()->subDays(7))->latest('analyzed_at')->limit(8)->get(),
            'today' => $today,
            'weeklyGoals' => $weeklyGoals,
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
