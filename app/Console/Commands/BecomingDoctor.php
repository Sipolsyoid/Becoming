<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BecomingDoctor extends Command
{
    protected $signature = 'becoming:doctor {--services : Check Ollama and runtime heartbeats} {--strict : Fail on warnings too}';

    protected $description = 'Check deployment prerequisites without sending mail or changing user data';

    public function handle(): int
    {
        $checks = [];
        $check = function (string $status, string $message) use (&$checks) {
            $checks[] = $status;
            $this->line(strtoupper($status).': '.$message);
        };
        $check(config('app.key') ? 'ok' : 'fail', 'Application encryption key configured (value hidden).');
        $check(app()->environment('production') && config('app.debug') ? 'fail' : 'ok', 'Debug configuration matches environment.');
        try {
            DB::select('SELECT 1');
            $files = app('migrator')->getMigrationFiles([database_path('migrations')]);
            $applied = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $missing = array_diff(array_keys($files), $applied);
            $check($missing ? 'fail' : 'ok', count($missing).' pending database migrations.');
        } catch (Throwable) {
            $check('fail', 'Database unavailable. Check your private database configuration.');
        }
        $check(is_dir(storage_path('app/private')) && is_writable(storage_path('app/private')) ? 'ok' : 'fail', 'Private photo storage directory exists and is writable.');
        $check(config('queue.connections.photos.driver') === 'database'
            && config('queue.connections.photos.retry_after') > 210 ? 'ok' : 'fail', 'Photo queue uses the database with retry interval longer than job timeout.');
        $check(in_array(config('mail.default'), ['log', 'array'], true) ? 'warn' : 'ok',
            in_array(config('mail.default'), ['log', 'array'], true) ? 'Email is preview only. Configure real delivery before demonstrating inbox reminders.' : 'Outgoing mail transport selected; actual delivery still requires an inbox test.');
        if ($this->option('services')) {
            try {
                $tags = Http::connectTimeout(3)->timeout(5)->get(rtrim(config('services.ollama.base_url'), '/').'/api/tags');
                $model = config('services.ollama.model');
                $available = $tags->successful() && collect($tags->json('models') ?? [])->contains(fn ($item) => ($item['name'] ?? null) === $model);
                $check($available ? 'ok' : 'fail', 'Configured vision model available in Ollama.');
            } catch (Throwable) {
                $check('fail', 'Ollama unavailable. Start the service and install the configured model.');
            }
            try {
                $check(Cache::has('photos.worker_seen_at') ? 'ok' : 'warn', 'Photo worker heartbeat: start/restart queue:work photos --queue=photos if absent.');
                $check(Cache::has('becoming.scheduler_seen_at') ? 'ok' : 'warn', 'Scheduler heartbeat: keep schedule:work running locally or invoke schedule:run every minute.');
            } catch (Throwable) {
                $check('fail', 'Cache unavailable; worker health and request limits need a working cache.');
            }
        }

        return in_array('fail', $checks, true) || ($this->option('strict') && in_array('warn', $checks, true)) ? self::FAILURE : self::SUCCESS;
    }
}
