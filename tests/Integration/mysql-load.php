<?php

use App\Models\Habit;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

define('BECOMING_INTEGRATION_LIBRARY', true);
require __DIR__.'/mysql-concurrency.php';

if (($argv[1] ?? null) === '--load-child') {
    [$database, $root, $userId, $habitId, $otherHabitId, $label] = array_slice($argv, 2);
    testDatabase($database);
    isolatedStorage($root);
    config(['app.debug' => false, 'mail.default' => 'array']);
    app()->instance('env', 'testing'); // The HTTP kernel runs auth/session/throttle; CSRF is bypassed as in Laravel tests.
    Auth::guard('web')->setUser(User::findOrFail($userId));
    File::put($root.'/'.$label.'-ready', 'ready');
    $deadline = microtime(true) + 45;
    while (! is_file($root.'/start')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Load barrier timed out.');
        }
        usleep(10000);
        clearstatcache();
    }
    $kernel = app(HttpKernel::class);
    $results = [];
    foreach (['/progress' => 200, '/history' => 200, '/habits' => 200, '/habits/'.$otherHabitId => 404,
        '/habits/'.$habitId.'/complete-with-photo' => 202] as $path => $expected) {
        $upload = str_ends_with($path, '/complete-with-photo');
        $request = Request::create($path, $upload ? 'POST' : 'GET', [], [],
            $upload ? ['photo' => new UploadedFile(public_path('img/logo.jpeg'), 'proof.jpg', 'image/jpeg', null, true)] : []);
        $request->headers->set('Accept', $upload ? 'application/json' : 'text/html');
        $started = microtime(true);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $results[] = ['route' => $upload ? 'upload' : ($expected === 404 ? 'owner isolation' : $path),
            'status' => $response->getStatusCode(), 'expected' => $expected,
            'milliseconds' => round((microtime(true) - $started) * 1000, 2)];
    }
    File::put($root.'/'.$label.'-result.json', json_encode(['requests' => $results, 'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2)]));
    exit(0);
}

$options = getopt('', ['users:', 'output:']);
$users = filter_var($options['users'] ?? 8, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 20]]);
if ($users === false) {
    throw new RuntimeException('Use 2 to 20 concurrent users.');
}
$output = $options['output'] ?? null;
if ($output && ! preg_match('/^docs\/[a-zA-Z0-9_\/\-]+\.json$/', $output)) {
    throw new RuntimeException('Output must be a JSON path under docs/.');
}
$database = 'becoming_test_concurrency_'.bin2hex(random_bytes(6));
$root = storage_path('framework/testing/concurrency-load-'.bin2hex(random_bytes(6)));
$created = false;
$processes = [];
try {
    if (DB::getDriverName() !== 'mysql') {
        throw new RuntimeException('Configure a MySQL test server first.');
    }
    DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    testDatabase($database);
    File::ensureDirectoryExists($root);
    isolatedStorage($root);
    Artisan::call('migrate', ['--force' => true]);
    $accounts = [];
    $createdAt = now()->subDays(364);
    for ($index = 0; $index < $users; $index++) {
        $user = User::factory()->create(['reminders_enabled' => false]);
        $firstHabit = null;
        for ($number = 0; $number < 200; $number++) {
            $habit = Habit::factory()->create(['user_id' => $user->id, 'is_daily' => $number < 30,
                'name' => 'Load habit '.$number, 'created_at' => $createdAt]);
            $firstHabit ??= $habit->id;
            $versions = [];
            for ($version = 1; $version <= 10; $version++) {
                $versions[] = ['habit_id' => $habit->id, 'effective_on' => $createdAt->copy()->addDays($version * 30)->toDateString(),
                    'is_active' => $number < 30, 'schedule_type' => 'daily', 'weekdays' => null, 'weekly_target' => null,
                    'created_at' => now(), 'updated_at' => now()];
            }
            DB::table('habit_schedule_versions')->insert($versions);
            if ($number < 30) {
                $completions = [];
                for ($day = 1; $day <= 30; $day++) {
                    $completions[] = ['habit_id' => $habit->id, 'user_id' => $user->id,
                        'completed_on' => now()->subDays($day)->toDateString(), 'ai_status' => 'approved',
                        'created_at' => now(), 'updated_at' => now()];
                }
                DB::table('habit_completions')->insert($completions);
            }
        }
        $accounts[] = ['user' => $user->id, 'habit' => $firstHabit];
    }
    echo 'Prepared '.$users.' isolated accounts, each with 200 habits, 30 active habits, 11 schedule versions per habit and 900 historical approvals.'.PHP_EOL;
    foreach ($accounts as $index => $account) {
        $other = $accounts[($index + 1) % $users]['habit'];
        $process = new Process([PHP_BINARY, __FILE__, '--load-child', $database, $root,
            (string) $account['user'], (string) $account['habit'], (string) $other, (string) $index], base_path());
        $process->setTimeout(120)->start();
        $processes[] = $process;
    }
    $deadline = microtime(true) + 45;
    while (count(glob($root.'/*-ready')) !== $users) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Load children failed to reach the barrier.');
        }
        usleep(10000);
        clearstatcache();
    }
    $started = microtime(true);
    File::put($root.'/start', 'start');
    $requests = [];
    $memory = [];
    foreach ($processes as $index => $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Load child failed: '.$process->getErrorOutput().$process->getOutput());
        }
        $result = json_decode(File::get($root.'/'.$index.'-result.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($result['requests'] as $request) {
            if ($request['status'] !== $request['expected']) {
                throw new RuntimeException('Unexpected '.$request['status'].' response for '.$request['route']);
            }
            $requests[] = $request;
        }
        $memory[] = $result['peak_memory_mb'];
    }
    if (DB::table('jobs')->count() !== $users || DB::table('habit_completions')->where('verification_status', 'queued')->count() !== $users
        || count(Storage::disk('local')->allFiles('habit-proofs')) !== $users) {
        throw new RuntimeException('Upload integrity failed under load.');
    }
    $times = array_column($requests, 'milliseconds');
    sort($times);
    $report = ['tested_at_utc' => now()->toIso8601String(), 'concurrent_users' => $users,
        'habits_per_user' => 200, 'active_habits_per_user' => 30, 'versions_per_habit' => 11, 'historical_approvals_per_user' => 900,
        'scope' => 'Separate PHP processes exercise the HTTP kernel with auth/session/throttle and rendered views. Test environment bypasses CSRF. No network server, real AI inference or SMTP throughput is measured.',
        'requests' => count($requests), 'unexpected_responses' => 0, 'wall_seconds' => round(microtime(true) - $started, 2),
        'p50_ms' => $times[(int) ceil(count($times) * .5) - 1], 'p95_ms' => $times[(int) ceil(count($times) * .95) - 1],
        'max_ms' => max($times), 'max_process_memory_mb' => max($memory), 'uploads' => $users, 'samples' => $requests];
    if ($output) {
        File::ensureDirectoryExists(dirname(base_path($output)));
        File::put(base_path($output), json_encode($report, JSON_PRETTY_PRINT).PHP_EOL);
    }
    echo 'PASS: '.$report['requests'].' requests; no unexpected responses, isolated owners, exactly '.$users.' queued photos. p95 '.$report['p95_ms'].'ms; wall '.$report['wall_seconds'].'s.'.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    $failed = true;
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    if ($created && preg_match('/\Abecoming_test_concurrency_[a-f0-9]{12}\z/', $database)) {
        DB::statement('DROP DATABASE `'.$database.'`');
    }
    if (is_dir($root)) {
        isolatedStorage($root);
        File::deleteDirectory($root);
    }
}
exit(isset($failed) ? 1 : 0);
