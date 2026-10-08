<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-[#2B3E51]">{{ $habit->name }}</h1></x-slot>
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 space-y-6">
        <a href="{{ route('habits.index') }}" class="text-sm font-semibold underline">Back to habits</a>
        <section class="rounded-2xl bg-white/80 border border-[#2B3E51]/10 p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-semibold break-words">{{ $habit->name }}</h2>
                <span class="status-pill">{{ $habit->archived_at ? 'Archived' : $habit->scheduleLabel() }}</span>
                @if($habit->category)<span class="text-sm">{{ $habit->category }}</span>@endif
            </div>
            <p class="mt-3 text-sm text-[#2B3E51]/70">Your photo check-ins, newest first. Photos are visible only to you. Open a photo to view it at full size.</p>
            <p class="mt-2 text-sm">One record is kept per date. Replacing a photo updates that day's record; earlier attempts are not retained.</p>
        </section>
        <section aria-labelledby="photo-history-heading">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 id="photo-history-heading" class="text-lg font-semibold">Photo history <span class="text-sm font-normal">({{ $checkIns->total() }})</span></h2>
                <a href="{{ route('habits.show', $habit) }}" class="underline text-sm">Refresh results</a>
            </div>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse($checkIns as $checkIn)
                    <article class="rounded-2xl border border-[#2B3E51]/10 bg-white/80 p-5 shadow-sm space-y-4">
                        <h3 class="font-semibold"><time datetime="{{ $checkIn->completed_on->toDateString() }}">{{ $checkIn->completed_on->format('j F Y') }}</time></h3>
                        @if($checkIn->photo_path)
                            <div>
                                <a href="{{ route('checks.photo', [$checkIn, 'saved']) }}" target="_blank" rel="noopener" class="block rounded-xl focus:ring-2 focus:ring-[#377991]">
                                    <img src="{{ route('checks.photo', [$checkIn, 'saved']) }}" alt="Saved photo for {{ $habit->name }} on {{ $checkIn->completed_on->format('j F Y') }}" loading="lazy" class="w-full h-56 object-contain rounded-xl bg-[#FAF8F5]">
                                    <span class="block mt-2 underline text-sm">Open full-size photo (new tab)</span>
                                </a>
                                <p class="mt-3 font-semibold">{{ Str::headline($checkIn->ai_status ?? 'Not reviewed') }}</p>
                                <p class="mt-1 text-sm break-words">{{ $checkIn->ai_reason ?: 'No AI feedback was recorded for this photo.' }}</p>
                                @if($checkIn->ai_status === 'needs_review')<p class="mt-2 text-sm">Evidence was not confirmed and earns no completion credit. No human review is scheduled. Submit clearer evidence when the habit is due.</p>@endif
                            </div>
                        @endif
                        @if($checkIn->pending_photo_path)
                            <div class="border-t border-[#2B3E51]/10 pt-3">
                                <p class="font-semibold">{{ $checkIn->photo_path ? 'Replacement photo' : 'Submitted photo' }}: {{ Str::headline($checkIn->verification_status) }}</p>
                                <a href="{{ route('checks.photo', [$checkIn, 'pending']) }}" target="_blank" rel="noopener" class="block mt-3">
                                    <img src="{{ route('checks.photo', [$checkIn, 'pending']) }}" alt="Photo awaiting verification for {{ $habit->name }}" loading="lazy" class="w-full h-56 object-contain rounded-xl bg-[#FAF8F5]">
                                    <span class="block mt-2 underline text-sm">Open submitted photo (new tab)</span>
                                </a>
                                <p class="mt-2 text-sm break-words">{{ $checkIn->verification_reason ?: 'Verification is in progress. Refresh this page to see the latest result.' }}</p>
                                @if($checkIn->photo_path && $checkIn->ai_status === 'approved')<p class="mt-2 text-sm">Your saved approval remains while this replacement is being checked.</p>@endif
                            </div>
                        @endif
                        @if(!$checkIn->photo_path && !$checkIn->pending_photo_path)
                            <p class="text-sm">No photo is available for this check-in.</p>
                            <p class="font-semibold">{{ Str::headline($checkIn->ai_status ?? 'Not reviewed') }}</p>
                            <p class="text-sm break-words">{{ $checkIn->ai_reason ?: $checkIn->verification_reason }}</p>
                        @endif
                    </article>
                @empty
                    <div class="md:col-span-2 xl:col-span-3 rounded-2xl bg-white/80 p-8 text-center">
                        <h3 class="font-semibold">No photo check-ins yet</h3>
                        <p class="mt-2 text-sm">Photos you submit for this habit will appear here with their verification results.</p>
                        @if(!$habit->archived_at)<a href="{{ route('dashboard') }}" class="action-button mt-4">Go to dashboard</a>@endif
                    </div>
                @endforelse
            </div>
            <div class="mt-6">{{ $checkIns->links() }}</div>
        </section>
    </div>
</x-app-layout>
