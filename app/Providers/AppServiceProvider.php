<?php

namespace App\Providers;

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Looping::class, function (Looping $event) {
            if ($event->connectionName === 'photos' && in_array('photos', explode(',', $event->queue), true)) {
                Cache::put('photos.worker_seen_at', now()->timestamp, now()->addMinutes(5));
            }
        });
    }
}
