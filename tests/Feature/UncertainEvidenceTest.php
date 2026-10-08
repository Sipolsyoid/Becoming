<?php

use App\Models\HabitCompletion;

test('uncertain evidence explains no credit and no promised human review', function () {
    $check = HabitCompletion::factory()->create(['completed_on' => today(), 'ai_status' => 'needs_review',
        'verification_status' => 'needs_review', 'photo_path' => 'habit-proofs/example.jpg']);
    $this->actingAs($check->user)->get(route('habits.show', $check->habit))->assertOk()
        ->assertSee('No human review is scheduled')->assertSee('earns no completion credit');
    $this->getJson('/')->assertJsonPath('completed', 0);
    $this->get('/')->assertSee('one photo cannot establish time spent');
});
