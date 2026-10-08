<x-app-layout>
    <x-slot name="header"><h1>Please take a short break</h1></x-slot>
    <div class="page-content"><section class="insight-card">
        <p>{{ $message ?? 'Too many requests. Please wait and try again.' }}</p>
        <p class="mt-3">Your saved photos and results are kept.</p>
        <a href="{{ route('dashboard') }}" class="action-button mt-4">Back to dashboard</a>
    </section></div>
</x-app-layout>
