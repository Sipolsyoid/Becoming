<?php

namespace App\Console\Commands;

use App\Contracts\PhotoVerifier;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Throwable;

class EvaluatePhotoVerifier extends Command
{
    protected $signature = 'photos:evaluate {manifest=tests/Fixtures/photo-evaluation.json} {--output= : JSON report path under docs/}';

    protected $description = 'Run labelled photo cases against the configured real AI verifier without changing user data';

    public function handle(PhotoVerifier $verifier): int
    {
        try {
            $cases = json_decode(File::get(base_path($this->argument('manifest'))), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($cases) || ! array_is_list($cases) || $cases === []) {
                throw new \RuntimeException('Manifest must be a nonempty list.');
            }
            $results = [];
            foreach ($cases as $case) {
                if (! is_array($case) || ! is_string($case['habit'] ?? null) || ! is_string($case['image'] ?? null)
                    || ! is_string($case['label'] ?? null) || ! is_array($case['allowed'] ?? null) || $case['allowed'] === []
                    || array_diff($case['allowed'], ['approved', 'needs_review', 'rejected'])) {
                    throw new \RuntimeException('Invalid evaluation case.');
                }
                $start = microtime(true);
                $photo = new UploadedFile(base_path($case['image']), basename($case['image']), null, null, true);
                try {
                    $result = $verifier->verify($photo, $case['habit']);
                    $passed = in_array($result['decision'], $case['allowed'], true);
                    $row = ['label' => $case['label'], 'passed' => $passed, 'verdict' => $result];
                } catch (Throwable $exception) {
                    $row = ['label' => $case['label'], 'passed' => false, 'error' => $exception->getMessage()];
                }
                $row['seconds'] = round(microtime(true) - $start, 2);
                $results[] = $row;
                $this->line(($row['passed'] ? 'PASS ' : 'FAIL ').$row['label'].' ('.$row['seconds'].'s): '.($row['verdict']['decision'] ?? $row['error']));
            }
            $report = ['model' => config('services.ollama.model'), 'tested_at_utc' => now()->toIso8601String(),
                'scope' => 'Small labelled smoke suite; not a general accuracy or security guarantee.', 'cases' => $results];
            if ($output = $this->option('output')) {
                if (! preg_match('/^docs\/[a-zA-Z0-9_\/\-]+\.json$/', $output)) {
                    throw new \RuntimeException('Report output must be a JSON path under docs/.');
                }
                File::ensureDirectoryExists(dirname(base_path($output)));
                File::put(base_path($output), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
            }

            return collect($results)->every('passed') ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
