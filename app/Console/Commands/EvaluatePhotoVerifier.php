<?php

namespace App\Console\Commands;

use App\Contracts\PhotoVerifier;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Throwable;

class EvaluatePhotoVerifier extends Command
{
    protected $signature = 'photos:evaluate {manifest=tests/Fixtures/photo-evaluation.json} {--images-root= : Local folder containing private evaluation images} {--repeat=1 : Runs per case, from 1 to 10} {--output= : JSON report path under docs/}';

    protected $description = 'Run labelled photo cases against the configured real AI verifier without changing user data';

    public function handle(PhotoVerifier $verifier): int
    {
        try {
            $cases = json_decode(File::get(base_path($this->argument('manifest'))), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($cases) || ! array_is_list($cases) || $cases === []) {
                throw new \RuntimeException('Manifest must be a nonempty list.');
            }
            $repeat = filter_var($this->option('repeat'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
            if ($repeat === false) {
                throw new \RuntimeException('Repeat must be an integer from 1 to 10.');
            }
            $imageRoot = $this->option('images-root') ? realpath($this->option('images-root')) : base_path();
            if (! $imageRoot || ! is_dir($imageRoot)) {
                throw new \RuntimeException('Image root must be an existing local directory.');
            }
            $results = [];
            foreach ($cases as $case) {
                if (! is_array($case) || ! is_string($case['habit'] ?? null) || ! is_string($case['image'] ?? null)
                    || ! is_string($case['label'] ?? null) || ! is_array($case['allowed'] ?? null) || $case['allowed'] === []
                    || array_diff($case['allowed'], ['approved', 'needs_review', 'rejected'])) {
                    throw new \RuntimeException('Invalid evaluation case.');
                }
                $root = ($case['source'] ?? null) === 'project' ? base_path() : $imageRoot;
                $photoPath = realpath($root.DIRECTORY_SEPARATOR.$case['image']);
                if (! $photoPath || ! str_starts_with($photoPath, realpath($root).DIRECTORY_SEPARATOR) || ! is_file($photoPath)) {
                    throw new \RuntimeException('Image must be an existing file inside its evaluation root.');
                }
                for ($run = 1; $run <= $repeat; $run++) {
                    $start = microtime(true);
                    $photo = new UploadedFile($photoPath, basename($case['image']), null, null, true);
                    try {
                        $result = $verifier->verify($photo, $case['habit']);
                        $passed = in_array($result['decision'], $case['allowed'], true);
                        $row = ['label' => $case['label'], 'passed' => $passed, 'verdict' => $result];
                    } catch (Throwable $exception) {
                        $row = ['label' => $case['label'], 'passed' => false, 'error' => $exception->getMessage()];
                    }
                    $row['seconds'] = round(microtime(true) - $start, 2);
                    $row['run'] = $run;
                    $row['allowed'] = $case['allowed'];
                    $row['image_sha256'] = hash_file('sha256', $photoPath);
                    $row['false_approval'] = ($row['verdict']['decision'] ?? null) === 'approved' && ! in_array('approved', $case['allowed'], true);
                    $row['false_refusal'] = isset($row['verdict']) && $row['verdict']['decision'] !== 'approved' && $case['allowed'] === ['approved'];
                    $results[] = $row;
                    $this->line(($row['passed'] ? 'PASS ' : 'FAIL ').$row['label'].' run '.$run.' ('.$row['seconds'].'s): '.($row['verdict']['decision'] ?? $row['error']));
                }
            }
            $report = ['model' => config('services.ollama.model'), 'tested_at_utc' => now()->toIso8601String(),
                'scope' => 'Labelled local evaluation; repeated cases are not independent images or a general accuracy/security guarantee.',
                'summary' => ['runs' => count($results), 'passed' => collect($results)->where('passed', true)->count(),
                    'false_approvals' => collect($results)->where('false_approval', true)->count(),
                    'false_refusals' => collect($results)->where('false_refusal', true)->count(),
                    'errors' => collect($results)->filter(fn ($row) => isset($row['error']))->count()], 'cases' => $results];
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
