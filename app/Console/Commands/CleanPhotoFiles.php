<?php

namespace App\Console\Commands;

use App\Models\HabitCompletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanPhotoFiles extends Command
{
    protected $signature = 'photos:cleanup {--delete : Delete unreferenced files; otherwise preview only}';

    protected $description = 'Find unreferenced private habit photos older than 24 hours';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $count = 0;
        foreach ($disk->allFiles('habit-proofs') as $path) {
            if ($disk->lastModified($path) >= now()->subDay()->timestamp
                || HabitCompletion::where('photo_path', $path)->orWhere('pending_photo_path', $path)->exists()) {
                continue;
            }
            if ($this->option('delete') && ! $disk->delete($path)) {
                $this->error('Could not delete '.$path);

                return self::FAILURE;
            }
            $count++;
        }
        $this->info($count.($this->option('delete') ? ' unreferenced photos deleted.' : ' unreferenced photos eligible for deletion (preview only).'));

        return self::SUCCESS;
    }
}
