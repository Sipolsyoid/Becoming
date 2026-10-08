<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('photo-checks', function (Request $request) {
            $response = function (Request $request, array $headers) {
                $message = 'You have reached the photo-check request limit. Please wait and try again.';

                return $request->expectsJson()
                    ? response()->json(['message' => $message, 'errors' => ['photo' => [$message]]], 429, $headers)
                    : response()->view('errors.429', ['message' => $message], 429, $headers);
            };

            return [
                Limit::perHour(max(1, config('habits.photo_requests_per_hour')))->by('photo-hour:'.$request->user()->id)->response($response),
                Limit::perDay(max(1, config('habits.photo_requests_per_day')))->by('photo-day:'.$request->user()->id)->response($response),
            ];
        });

        Event::listen(Looping::class, function (Looping $event) {
            if ($event->connectionName === 'photos' && in_array('photos', explode(',', $event->queue), true)) {
                Cache::put('photos.worker_seen_at', now()->timestamp, now()->addMinutes(5));
            }
        });
    }
}
