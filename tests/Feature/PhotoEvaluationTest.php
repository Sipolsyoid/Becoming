<?php

use App\Contracts\PhotoVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

test('evaluation fails visibly on unsafe verdicts without touching user records', function () {
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldReceive('verify')->times(4)->andReturn(['decision' => 'approved', 'reason' => 'Approved', 'visible_evidence' => 'Logo']);
    app()->instance(PhotoVerifier::class, $verifier);
    $this->artisan('photos:evaluate')->assertFailed();
    $this->assertDatabaseCount('habit_completions', 0);
});

test('repeated evaluation reports false approvals without publishing private image paths', function () {
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldReceive('verify')->times(8)->andReturn(['decision' => 'approved', 'reason' => 'Approved', 'visible_evidence' => 'Logo']);
    app()->instance(PhotoVerifier::class, $verifier);
    $output = 'docs/verification/mock-'.bin2hex(random_bytes(6)).'.json';
    try {
        expect(Artisan::call('photos:evaluate', ['--repeat' => 2, '--output' => $output]))->toBe(1);
        $report = json_decode(File::get(base_path($output)), true);
        expect($report['summary'])->toMatchArray(['runs' => 8, 'passed' => 2, 'false_approvals' => 6, 'false_refusals' => 0]);
        expect($report['cases'][0]['image_sha256'])->toBe(hash_file('sha256', public_path('img/logo.jpeg')));
        expect(File::get(base_path($output)))->not->toContain(base_path());
    } finally {
        File::delete(base_path($output));
    }
});

test('evaluation refuses invalid repetition counts before contacting AI', function () {
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldNotReceive('verify');
    app()->instance(PhotoVerifier::class, $verifier);
    $this->artisan('photos:evaluate', ['--repeat' => 0])->assertFailed();
    $this->artisan('photos:evaluate', ['--repeat' => 11])->assertFailed();
});
