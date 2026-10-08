<?php

use App\Http\Controllers\HabitCompletionController;
use App\Http\Controllers\HabitController;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

// Independent processes and an isolated MySQL schema exercise real row locks.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function testDatabase(string $name): void
{
    if (! preg_match('/\Abecoming_test_concurrency_[a-f0-9]{12}\z/', $name)) {
        throw new RuntimeException('Unsafe test database name.');
    }
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name,
        'database.connections.mysql.url' => null, 'queue.connections.photos.connection' => 'mysql', 'queue.connections.photos.driver' => 'database']);
    DB::purge('mysql');
}

function isolatedStorage(string $root): void
{
    $base = realpath(storage_path('framework/testing'));
    $resolved = realpath($root);
    if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR.'concurrency-')) {
        throw new RuntimeException('Unsafe test storage path.');
    }
    config(['filesystems.disks.local.root' => $resolved.'/photos', 'session.driver' => 'array', 'cache.default' => 'array']);
    Storage::forgetDisk('local');
}

if (($argv[1] ?? null) === '--child') {
    [$database, $root, $mode, $habitId, $userId, $label] = array_slice($argv, 2);
    testDatabase($database);
    isolatedStorage($root);
    config(['habits.max_active' => 1]);
    $barrier = $root.'/'.$mode;
    File::put($barrier.'/'.$label.'-ready', 'ready');
    $deadline = microtime(true) + 20;
    while (! is_file($barrier.'/start')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Start barrier timed out.');
        }
        usleep(10000);
        clearstatcache();
    }
    $user = User::findOrFail($userId);
    $habit = Habit::findOrFail($habitId);
    $files = $mode === 'upload' ? ['photo' => new UploadedFile(public_path('img/logo.jpeg'), 'proof.jpg', 'image/jpeg', null, true)] : [];
    $request = Request::create('/', 'POST', $mode === 'activate' ? ['is_daily' => 1] : [], [], $files);
    $request->headers->set('Accept', 'application/json');
    $app->instance('request', $request);
    $request->setUserResolver(fn () => $user);
    try {
        $response = $mode === 'upload' ? app(HabitCompletionController::class)->submitPhoto($request, $habit)
            : app(HabitController::class)->update($request, $habit);
        File::put($barrier.'/'.$label.'-result.json', json_encode(['status' => $response->getStatusCode()]));
    } catch (ValidationException $exception) {
        File::put($barrier.'/'.$label.'-result.json', json_encode(['status' => 422, 'errors' => array_keys($exception->errors())]));
    } catch (Throwable $exception) {
        File::put($barrier.'/'.$label.'-result.json', json_encode(['status' => 500, 'error' => get_class($exception),
            'detail' => $exception instanceof ErrorException ? $exception->getMessage() : 'Controller operation failed.']));
    }
    exit(0);
}

function race(string $database, string $root, string $mode, array $habits, int $user): array
{
    $barrier = $root.'/'.$mode;
    File::ensureDirectoryExists($barrier);
    $processes = [];
    foreach ($habits as $index => $id) {
        $process = new Process([PHP_BINARY, __FILE__, '--child', $database, $root, $mode, (string) $id, (string) $user, (string) $index], base_path());
        $process->setTimeout(40)->start();
        $processes[] = $process;
    }
    try {
        $deadline = microtime(true) + 20;
        while (! is_file($barrier.'/0-ready') || ! is_file($barrier.'/1-ready')) {
            if (microtime(true) > $deadline) {
                foreach ($processes as $process) {
                    $process->stop();
                }
                throw new RuntimeException('Children failed to reach the concurrency barrier.');
            }
            usleep(10000);
            clearstatcache();
        }
        File::put($barrier.'/start', 'start');
        $statuses = [];
        foreach ($processes as $index => $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Child failed: '.$process->getErrorOutput().$process->getOutput());
            }
            $result = json_decode(File::get($barrier.'/'.$index.'-result.json'), true, 512, JSON_THROW_ON_ERROR);
            if ($result['status'] === 500) {
                throw new RuntimeException('Child controller failed: '.$result['error'].' '.$result['detail']);
            }
            $statuses[] = $result['status'];
        }
        sort($statuses);

        return $statuses;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}

$database = 'becoming_test_concurrency_'.bin2hex(random_bytes(6));
$root = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(6)));
$created = false;
try {
    if (DB::getDriverName() !== 'mysql') {
        throw new RuntimeException('Configure a MySQL test server first. This script cannot test locks on SQLite.');
    }
    DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    testDatabase($database);
    File::ensureDirectoryExists($root);
    isolatedStorage($root);
    Artisan::call('migrate', ['--force' => true]);
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id]);
    try {
        DB::table('habits')->where('id', $habit->id)->update(['schedule_type' => 'weekdays', 'weekdays' => '[1.0]']);
        throw new RuntimeException('MySQL accepted a fractional JSON weekday that strict schedule comparisons cannot use.');
    } catch (QueryException) {
        echo "PASS: MySQL rejects fractional JSON weekdays.\n";
    }
    if (race($database, $root, 'upload', [$habit->id, $habit->id], $user->id) !== [202, 422]
        || $habit->completions()->count() !== 1 || DB::table('jobs')->count() !== 1
        || count(Storage::disk('local')->allFiles('habit-proofs')) !== 1) {
        throw new RuntimeException('Duplicate-upload concurrency invariant failed.');
    }
    echo "PASS: simultaneous uploads save one check-in, one job and one photo.\n";
    $habit->update(['is_daily' => false]);
    $second = Habit::factory()->create(['user_id' => $user->id, 'is_daily' => false]);
    if (race($database, $root, 'activate', [$habit->id, $second->id], $user->id) !== [302, 422]
        || $user->habits()->where('is_daily', true)->count() !== 1) {
        throw new RuntimeException('Active-habit capacity concurrency invariant failed.');
    }
    echo "PASS: simultaneous activations cannot exceed the owner's active-habit cap.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $failed = true;
} finally {
    if ($created && preg_match('/\Abecoming_test_concurrency_[a-f0-9]{12}\z/', $database)) {
        DB::statement('DROP DATABASE `'.$database.'`');
    }
    if (is_dir($root)) {
        isolatedStorage($root); // Check the absolute target before recursive removal.
        File::deleteDirectory($root);
    }
}
exit(isset($failed) ? 1 : 0);
