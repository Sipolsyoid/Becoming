<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#2B3E51] leading-tight">
            {{ __('Progress') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_700ms_ease-out_both]">
                    <div class="relative p-6 sm:p-8">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Weekly completion') }}</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <div class="text-4xl font-semibold tracking-tight text-[#2B3E51]">{{ $weeklyPercent }}%</div>
                            <div class="text-sm text-[#2B3E51]/60">{{ __('last 7 days') }}</div>
                        </div>
                        <p class="mt-3 text-sm text-[#2B3E51]/70">{{ __('Keep it simple: small wins add up.') }}</p>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_800ms_ease-out_both]">
                    <div class="relative p-6 sm:p-8">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Best streak') }}</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <div class="text-4xl font-semibold tracking-tight text-[#2B3E51]">{{ $bestStreak }}</div>
                            <div class="text-2xl">🔥</div>
                        </div>
                        <p class="mt-3 text-sm text-[#2B3E51]/70">{{ __('Perfect days in a row.') }}</p>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_900ms_ease-out_both]">
                    <div class="relative p-6 sm:p-8">
                        <p class="text-sm text-[#2B3E51]/70">{{ __('Improve next') }}</p>

                        @if ($weakHabit)
                            <p class="mt-2 text-lg font-semibold text-[#2B3E51]">
                                {{ $weakHabit->name }}
                            </p>
                            <p class="mt-1 text-sm text-[#2B3E51]/70">
                                {{ __('Completed :count/7 this week.', ['count' => $weakHabitCount]) }}
                            </p>
                        @else
                            <p class="mt-2 text-sm text-[#2B3E51]/70">
                                {{ __('Add daily habits to start seeing what to improve.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-[#2B3E51]/10 bg-white/70 shadow-xl backdrop-blur motion-safe:animate-[becoming-rise-in_700ms_ease-out_both]">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-[#76C7B7]/20 blur-3xl motion-safe:animate-[becoming-blob-2_18s_ease-in-out_infinite]"></div>
                    <div class="absolute -bottom-28 -left-28 h-72 w-72 rounded-full bg-[#5DA068]/10 blur-3xl motion-safe:animate-[becoming-blob-1_22s_ease-in-out_infinite]"></div>
                </div>

                <div class="relative p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-[#2B3E51]">{{ __('Last 7 days') }}</h3>
                            <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('A quick glance — no complicated analytics.') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-7 gap-3 items-end">
                        @foreach ($chartDays as $day)
                            <div class="flex flex-col items-center gap-2">
                                <div class="relative h-28 w-full overflow-hidden rounded-2xl bg-[#2B3E51]/10">
                                    @php($height = max(6, $day['percent']))
                                    <div class="absolute bottom-0 left-0 right-0 rounded-2xl bg-gradient-to-t from-[#377991] to-[#5DA068]" style="height: {{ $height }}%;"></div>
                                </div>
                                <div class="text-xs font-medium text-[#2B3E51]/80">{{ $day['label'] }}</div>
                                <div class="text-[10px] text-[#2B3E51]/60">{{ $day['done'] }}/{{ $day['total'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
