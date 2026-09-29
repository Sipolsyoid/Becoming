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
                                <div class="text-sm font-medium text-[#2B3E51]/70">{{ $percent }}%<span class="block text-xs font-normal">{{ $day['total'] === 0 ? 'No daily habits' : ($day['perfect'] ? 'Complete' : ($day['date']->isToday() ? 'In progress' : 'Incomplete')) }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
<p class="quiet-note max-w-7xl mx-auto px-6 pb-6">History uses your current daily habits. Changing your daily list also changes past percentages. Dates use UTC.</p>
</x-app-layout>
