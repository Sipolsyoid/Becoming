<x-app-layout>
<x-slot name="header">
<h1>Today is a fresh start.</h1>
</x-slot>
<div class="page-content">
<section class="today-banner">
<div>
<p class="eyebrow">YOUR DAILY RHYTHM</p>
<h2>{{ $totalCount > 0 && $completedCount === $totalCount ? 'You showed up for yourself.' : 'Small steps, every day.' }}</h2>
<p>{{ $totalCount > 0 && $completedCount === $totalCount ? 'All your daily habits are complete. Take a moment to enjoy it.' : 'Choose one habit. Give it your attention. Build from there.' }}</p>
<a href="{{ route('habits.index') }}" class="banner-link">Manage your habits ↗</a>
</div>
<div class="daily-score">
<strong>{{ $progressPercent }}<small>%</small>
</strong>
<span>{{ $completedCount }} of {{ $totalCount }} complete</span>
<progress max="100" value="{{ $progressPercent }}" aria-label="Today's habit completion">
</progress>
</div>
</section>
@if ($errors->has('photo'))<div class="error-message" role="alert">{{ $errors->first('photo') }}</div>@endif
<div class="dashboard-grid">
<section>
<div class="section-heading">
<h2>Your habits</h2>
<span>{{ $totalCount - $completedCount }} remaining</span>
</div>
<div class="space-y-4">
@forelse ($dailyHabits as $habit)
@php($isDone = in_array($habit->id, $completedHabitIds, true))
<article class="habit-card {{ $isDone ? 'habit-complete' : '' }}">
<div class="habit-title">
<span class="habit-marker" aria-hidden="true">{{ $isDone ? '✓' : sprintf('%02d', $loop->iteration) }}</span>
<div class="min-w-0">
<h3>{{ $habit->name }}</h3>
<p>{{ $habit->category ?: 'Your daily practice' }}</p>
</div>@if ($isDone)<span class="status-pill">Done</span>@endif</div>@unless ($isDone)<x-photo-proof :habit="$habit" />@endunless</article>
@empty
<div class="empty-panel">
<span aria-hidden="true" class="empty-symbol">＋</span>
<h3>Your next chapter starts small.</h3>
<p>Add one daily habit you want to make time for.</p>
<a class="action-button" href="{{ route('habits.index') }}">Add your first habit</a>
</div>
@endforelse
</div>
</section>
<aside class="space-y-5">
<div class="insight-card">
<p class="eyebrow">KEEP YOUR MOMENTUM</p>
<div class="streak-number">{{ $streak }} <span>days</span>
</div>
<h3>Your current streak</h3>
<p>Days in a row with every daily habit approved. Today counts once it’s complete.</p>
<a href="{{ route('progress') }}">Explore your progress →</a>
</div>
<div class="insight-card">
<p class="eyebrow">A LITTLE EXTRA ATTENTION</p>
<h3>{{ $focusHabit?->name ?? 'Find your rhythm' }}</h3>
<p>{{ $focusHabit ? 'Completed '.$focusHabitCount.' of the last 7 days. Make a little space for it today.' : 'Your least-completed daily habit will appear here once you add a habit.' }}</p>
</div>
<p class="quiet-note">Your photos are private. Only approved checks count toward your progress.</p>
</aside>
</div>
</div>
</x-app-layout>
