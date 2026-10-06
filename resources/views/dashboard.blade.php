<x-app-layout>
<x-slot name="header">
<h1>Today is a fresh start.</h1>
</x-slot>
<div x-data="liveProgress(@js($liveProgress), @js(route('dashboard')))" @photo-checked.window="refresh()">
<div class="page-content">
<section class="today-banner">
<div>
<p class="eyebrow">YOUR DAILY RHYTHM</p>
<h2 x-text="stats.total > 0 && stats.completed === stats.total ? 'You showed up for yourself.' : 'Small steps, every day.'">{{ $totalCount > 0 && $completedCount === $totalCount ? 'You showed up for yourself.' : 'Small steps, every day.' }}</h2>
<p x-text="stats.total > 0 && stats.completed === stats.total ? 'All habits scheduled for today are complete. Take a moment to enjoy it.' : 'Choose one habit. Give it your attention. Build from there.'">{{ $totalCount > 0 && $completedCount === $totalCount ? 'All habits scheduled for today are complete. Take a moment to enjoy it.' : 'Choose one habit. Give it your attention. Build from there.' }}</p>
<a href="{{ route('habits.index') }}" class="banner-link">Manage your habits â†—</a>
</div>
<div class="daily-score">
<strong><span x-text="stats.percent">{{ $progressPercent }}</span><small>%</small>
</strong>
<span x-text="stats.completed + ' of ' + stats.total + ' complete'">{{ $completedCount }} of {{ $totalCount }} complete</span>
<progress max="100" :value="stats.percent" value="{{ $progressPercent }}" aria-label="Today's habit completion">
</progress>
</div>
</section>
@if ($errors->has('photo'))<div class="error-message" role="alert">{{ $errors->first('photo') }}</div>@endif
<div class="dashboard-grid">
<section>
<div class="section-heading">
<h2>Scheduled today</h2>
<span x-text="(stats.total - stats.completed) + ' remaining'">{{ $totalCount - $completedCount }} remaining</span>
</div>
<div class="space-y-4">
@forelse ($dailyHabits as $habit)
@php($isDone = in_array($habit->id, $completedHabitIds, true))
<article class="habit-card {{ $isDone ? 'habit-complete' : '' }}" :class="{ 'habit-complete': stats.ids.includes({{ $habit->id }}) }">
<div class="habit-title">
<span class="habit-marker" aria-hidden="true" x-text="stats.ids.includes({{ $habit->id }}) ? 'âœ“' : '{{ sprintf('%02d', $loop->iteration) }}'">{{ $isDone ? 'âœ“' : sprintf('%02d', $loop->iteration) }}</span>
<div class="min-w-0">
<h3><a href="{{ route('habits.show', $habit) }}" class="underline underline-offset-4">{{ $habit->name }}</a></h3>
<p>{{ $habit->category ?: 'Your daily practice' }}</p>
</div><span x-cloak x-show="stats.ids.includes({{ $habit->id }})" class="status-pill">Done âœ“</span></div>@unless ($isDone)<x-photo-proof :habit="$habit" :check="$photoChecks->get($habit->id)" />@endunless</article>
@empty
<div class="empty-panel">
<span aria-hidden="true" class="empty-symbol">ï¼‹</span>
<h3>Room to breathe.</h3>
<p>Enjoy a rest day, work on a weekly goal below, or add another habit.</p>
<a class="action-button" href="{{ route('habits.index') }}">Choose your next habit</a>
</div>
@endforelse
</div>
</section>
<aside class="space-y-5">
<div class="insight-card">
<p class="eyebrow">KEEP YOUR MOMENTUM</p>
<div class="streak-number"><span x-text="stats.streak" style="font:inherit;color:inherit">{{ $streak }}</span> <span>days</span>
</div>
<h3>Your current streak</h3>
<p>Completed scheduled days. Today can still be finished, so yesterdayâ€™s streak stays alive until your local day ends. Rest days preserve it.</p>
<a href="{{ route('progress') }}">Explore your progress â†’</a>
</div>
<div class="insight-card">
<p class="eyebrow">A LITTLE EXTRA ATTENTION</p>
<h3 x-text="stats.focusName">{{ $focusHabit?->name ?? 'Find your rhythm' }}</h3>
<p x-text="stats.focusText">{{ $focusHabit ? 'Completed '.$focusHabitCount.' of '.$focusHabitTarget.' scheduled days in the last 7 days. Make a little space for it today.' : 'Your least-completed habit scheduled today will appear here once you add a habit.' }}</p>
</div>
<p class="quiet-note">Your photos are private. Only approved checks count toward your progress.</p>
</aside>
</div>
</div>
<div class="page-content pt-0"><x-weekly-goals :goals="$weeklyGoals" :completed-today="$weeklyCompletedToday" :upload="true" :checks="$photoChecks" /></div>
@if ($earlierChecks->isNotEmpty())
<section class="page-content pt-0">
    <div class="section-heading"><h2>Earlier photo checks</h2><span>Your saved check-ins</span></div>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($earlierChecks as $check)
        <article class="habit-card"><h3 class="font-semibold">{{ $check->habit->name }}</h3><x-photo-proof :habit="$check->habit" :check="$check" :allow-upload="false" /></article>
        @endforeach
    </div>
</section>
@endif
@if ($recentChecks->isNotEmpty())
<section class="page-content pt-0">
    <div class="section-heading"><h2>Recent photo results</h2><span>Checked in the last seven days</span></div>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($recentChecks as $check)
        <article class="habit-card"><h3 class="font-semibold"><a class="underline" href="{{ route('habits.show', $check->habit) }}">{{ $check->habit->name }}</a></h3>
            <p class="text-sm">{{ $check->completed_on->format('j M Y') }} · {{ Str::headline($check->verification_status) }}</p>
            <p class="mt-2 text-sm">{{ $check->verification_reason ?: $check->ai_reason }}</p>
            <a class="underline text-sm" href="{{ route('habits.show', $check->habit) }}">View photo history</a>
        </article>
        @endforeach
    </div>
</section>
@endif
<p x-cloak x-show="refreshError" class="feedback-message" role="status">Your photo result is saved. <a class="underline" href="{{ route('dashboard') }}">Refresh progress</a> when your connection returns.</p>
</div>
</x-app-layout>
