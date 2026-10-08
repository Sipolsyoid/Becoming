<?php

use App\Contracts\PhotoVerifier;

test('evaluation fails visibly on unsafe verdicts without touching user records', function () {
    $verifier = Mockery::mock(PhotoVerifier::class);
    $verifier->shouldReceive('verify')->times(4)->andReturn(['decision' => 'approved', 'reason' => 'Approved', 'visible_evidence' => 'Logo']);
    app()->instance(PhotoVerifier::class, $verifier);
    $this->artisan('photos:evaluate')->assertFailed();
    $this->assertDatabaseCount('habit_completions', 0);
});
