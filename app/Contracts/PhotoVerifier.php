<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface PhotoVerifier
{
    /** @return array{decision: string, reason: string, visible_evidence: string} */
    public function verify(UploadedFile $photo, string $habitName): array;
}
