<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-[#2B3E51] leading-tight">
            {{ __('History') }}
        </h1>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_700ms_ease-out_both]">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-[#76C7B7]/20 blur-3xl motion-safe:animate-[becoming-blob-2_18s_ease-in-out_infinite]"></div>
                    <div class="absolute -bottom-28 -left-28 h-72 w-72 rounded-full bg-[#377991]/10 blur-3xl motion-safe:animate-[becoming-blob-1_22s_ease-in-out_infinite]"></div>
                </div>

                <div class="relative p-6 sm:p-8">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2B3E51]">{{ __('Last 30 days') }}</h3>
                            <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('A record of your small wins. Today is still in progress.') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 divide-y divide-[#2B3E51]/10">
                        @foreach ($days as $day)
                            @php($percent = $day['total'] > 0 ? (int) round(($day['done'] / $day['total']) * 100) : 0)
                            <div class="flex items-center justify-between gap-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="text-lg">
                                        <span aria-hidden="true">{{ $day['perfect'] ? '✓' : '·' }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-[#2B3E51]">{{ $day['date']->isToday() ? 'Today' : $day['date']->format('D, M j') }}</div>
                                        <div class="text-xs text-[#2B3E51]/60">{{ $day['done'] }}/{{ $day['total'] }} {{ __('habits') }}</div>
                                    </div>
                                </div>
                                <div class="text-sm font-medium text-[#2B3E51]/70">{{ $percent }}%<span class="block text-xs font-normal">{{ $day['total'] === 0 ? 'Rest day' : ($day['perfect'] ? 'Complete' : ($day['date']->isToday() ? 'In progress' : 'Incomplete')) }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
<section class="page-content pt-0">
    <div class="section-heading"><h2>Weekly goal history</h2><span>Last 8 weeks · Monday–Sunday</span></div>
    <p class="quiet-note mb-4">Each week uses its recorded weekly schedule. Partial weeks keep the full target; only approvals on active weekly-goal days count.</p>
    <div class="space-y-4">
    @forelse ($weeklyHistory->filter(fn ($week) => $week['goals']->isNotEmpty()) as $week)
        <article class="habit-card">
            <h3 class="font-semibold">{{ $week['start']->format('M j') }} – {{ $week['end']->format('M j, Y') }}{{ $week['current'] ? ' · This week' : '' }}</h3>
            @foreach ($week['goals'] as $goal)
                <div class="flex flex-wrap justify-between gap-3 mt-4 border-t pt-3">
                    <div><p class="font-medium">{{ $goal['habit']->name }}</p><p class="text-xs text-slate-500">{{ $goal['done'] }} / {{ $goal['target'] }} approved{{ $goal['partial'] ? ' · Partial schedule week' : '' }}</p></div>
                    <span class="status-pill">{{ $goal['reached'] ? 'Goal reached' : ($week['current'] ? 'In progress' : 'Not reached') }}</span>
                </div>
            @endforeach
        </article>
    @empty
        <p class="empty-panel">Your weekly goals will appear here once you add a habit with a weekly target.</p>
    @endforelse
    </div>
</section>
<p class="quiet-note max-w-7xl mx-auto px-6 pb-6">Days before a habit was created do not count. Schedule edits and pauses apply from their local effective date, leaving earlier days unchanged. Rest days preserve streaks; an unfinished today does not break yesterday’s streak. Dates use {{ auth()->user()->timezone }}.</p>
</x-app-layout>
