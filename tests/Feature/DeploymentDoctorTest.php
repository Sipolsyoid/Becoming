<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('doctor reports an unavailable model and missing runtime without sending notifications', function () {
    Http::fake(['*' => Http::response(['models' => []])]);
    Cache::forget('photos.worker_seen_at');
    Cache::forget('becoming.scheduler_seen_at');
    $this->artisan('becoming:doctor --services')->expectsOutput('FAIL: Configured vision model available in Ollama.')->assertFailed();
});

test('doctor recognizes the configured model and both runtime heartbeats', function () {
    config(['mail.default' => 'smtp']);
    Http::fake(['*' => Http::response(['models' => [['name' => config('services.ollama.model')]]])]);
    Cache::put('photos.worker_seen_at', now()->timestamp, 300);
    Cache::put('becoming.scheduler_seen_at', now()->timestamp, 180);
    $this->artisan('becoming:doctor --services --strict')->assertSuccessful();
});
