<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitMetrics;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 25)->startOfDay());
    Http::preventStrayRequests();
});

function proofUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('proof.jpg', file_get_contents(public_path('img/logo.jpeg')));
}

function fakeVerdict(string $decision = 'approved'): void
{
    Http::fake(['*' => Http::response(['message' => ['content' => json_encode([
        'decision' => $decision, 'reason' => 'Photo assessment.', 'visible_evidence' => 'Visible evidence.',
    ])]])]);
}

test('habits validate input, belong to their creator and can be toggled and deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/habits', ['name' => 'Reading', 'category' => 'Learning', 'is_daily' => 1])->assertRedirect();
    $habit = $user->habits()->sole();
    $this->post('/habits', ['name' => 'Reading'])->assertSessionHasErrors('name');
    $this->post('/habits', ['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
    $this->post('/habits', ['name' => ''])->assertSessionHasErrors('name');
    $this->post('/habits', ['name' => 'Walking', 'category' => str_repeat('a', 41)])->assertSessionHasErrors('category');
    $this->patch("/habits/{$habit->id}", ['is_daily' => 'invalid'])->assertSessionHasErrors('is_daily');
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today()]);
    $this->patch("/habits/{$habit->id}", ['is_daily' => 0])->assertRedirect();
    expect((bool) $habit->fresh()->is_daily)->toBeFalse();
    expect($habit->completions()->count())->toBe(1);
    $this->delete("/habits/{$habit->id}")->assertRedirect();
    $this->assertDatabaseMissing('habits', ['id' => $habit->id]);
    $this->assertDatabaseCount('habit_completions', 0);
});

test('users cannot access another users habits or submit their photos', function () {
    $habit = Habit::factory()->create(['name' => 'Private habit']);
    $this->actingAs(User::factory()->create());
    $this->get('/habits')->assertOk()->assertDontSee('Private habit');
    $this->patch("/habits/{$habit->id}", ['is_daily' => 0])->assertNotFound();
    $this->delete("/habits/{$habit->id}")->assertNotFound();
    $this->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])->assertNotFound();
    expect($habit->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

test('guest pages require authentication', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/', '/habits', '/history', '/progress']);

test('photo verdicts are persisted and only approved verdicts count', function (string $decision) {
    Storage::fake('local');
    fakeVerdict($decision);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->from('/')->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])
        ->assertRedirect('/')->assertSessionHasNoErrors();
    $completion = $habit->completions()->sole();
    expect($completion->ai_status)->toBe($decision);
    Storage::disk('local')->assertExists($completion->photo_path);
    $this->get('/')->assertOk()->assertViewHas('completedCount', $decision === 'approved' ? 1 : 0);
    Http::assertSent(fn ($request) => $request['stream'] === false
        && $request['messages'][1]['images'][0] !== ''
        && $request['format']['required'] === ['decision', 'reason', 'visible_evidence']);
})->with(['approved', 'needs_review', 'rejected']);

test('a retry replaces the same days record and removes the old photo', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::sequence()
        ->push(['message' => ['content' => '{"decision":"approved","reason":"yes","visible_evidence":"x"}']])
        ->push(['message' => ['content' => '{"decision":"rejected","reason":"no","visible_evidence":"x"}']])]);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user);
    $url = "/habits/{$habit->id}/complete-with-photo";
    $this->post($url, ['photo' => proofUpload()])->assertSessionHasNoErrors();
    $old = $habit->completions()->sole();
    $this->post($url, ['photo' => proofUpload()])->assertSessionHasNoErrors();
    $new = $habit->completions()->sole();
    expect($new->id)->toBe($old->id);
    expect($new->ai_status)->toBe('rejected');
    Storage::disk('local')->assertMissing($old->photo_path);
    Storage::disk('local')->assertExists($new->photo_path);
});

test('invalid AI responses preserve the previous completion and clean up the new upload', function (string $content) {
    Storage::fake('local');
    $habit = Habit::factory()->create();
    Storage::disk('local')->put('old.jpg', 'old proof');
    $old = HabitCompletion::factory()->create([
        'habit_id' => $habit->id, 'completed_on' => today(), 'photo_path' => 'old.jpg', 'ai_status' => 'approved',
    ]);
    Http::fake(['*' => Http::response(['message' => ['content' => $content]])]);
    $this->actingAs($habit->user)->from('/')->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])
        ->assertRedirect('/')->assertSessionHasErrors('photo');
    expect($old->fresh()->ai_status)->toBe('approved');
    expect($old->fresh()->photo_path)->toBe('old.jpg');
    expect(Storage::disk('local')->allFiles())->toBe(['old.jpg']);
})->with([
    'bad JSON' => '{',
    'scalar' => 'null',
    'missing evidence' => '{"decision":"approved","reason":"yes"}',
    'wrong type' => '{"decision":"approved","reason":[],"visible_evidence":"x"}',
    'invalid decision' => '{"decision":"maybe","reason":"x","visible_evidence":"x"}',
    'extra property' => '{"decision":"approved","reason":"x","visible_evidence":"x","extra":true}',
]);

test('Ollama connection failures preserve existing data and clean up new files', function () {
    Storage::fake('local');
    Http::fake(['*' => Http::failedConnection()]);
    $habit = Habit::factory()->create();
    Storage::disk('local')->put('old.jpg', 'old proof');
    $old = HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today(), 'photo_path' => 'old.jpg', 'ai_status' => 'approved']);
    $this->actingAs($habit->user)->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])->assertSessionHasErrors('photo');
    expect($old->fresh()->photo_path)->toBe('old.jpg');
    expect(Storage::disk('local')->allFiles())->toBe(['old.jpg']);
});

test('failed storage never invokes AI or updates a completion', function (bool $throws) {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $write = $disk->shouldReceive('putFileAs')->once();
    $throws ? $write->andThrow(new RuntimeException('Internal storage details')) : $write->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user)->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])
        ->assertSessionHasErrors(['photo' => 'The photo could not be saved. Please try again.']);
    $this->assertDatabaseCount('habit_completions', 0);
    Http::assertNothingSent();
})->with([false, true]);

test('cleanup failure after saving does not delete the new proof or lose the verdict', function (bool $throws) {
    fakeVerdict();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn('new.jpg');
    $delete = $disk->shouldReceive('delete')->with('old.jpg')->once();
    $throws ? $delete->andThrow(new RuntimeException('Cleanup failed')) : $delete->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $habit = Habit::factory()->create();
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today(), 'photo_path' => 'old.jpg']);
    $this->actingAs($habit->user)->post("/habits/{$habit->id}/complete-with-photo", ['photo' => proofUpload()])->assertSessionHasNoErrors();
    expect($habit->completions()->sole()->photo_path)->toBe('new.jpg');
    expect($habit->completions()->sole()->ai_status)->toBe('approved');
})->with([false, true]);

test('invalid uploads are rejected before inference', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user);
    foreach ([UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'), proofUpload()->size(6144)] as $file) {
        $this->post("/habits/{$habit->id}/complete-with-photo", ['photo' => $file])->assertSessionHasErrors('photo');
    }
    Http::assertNothingSent();
    $this->assertDatabaseCount('habit_completions', 0);
});

test('reports retain documented dates, rounding, streaks and least completed ordering', function () {
    $user = User::factory()->create();
    $habits = collect(range(1, 3))->map(fn ($i) => Habit::factory()->create(['user_id' => $user->id, 'created_at' => today()->subDays(10 - $i)]));
    foreach ($habits as $habit) {
        foreach ([1, 2, 3] as $days) {
            HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today()->subDays($days), 'ai_status' => 'approved']);
        }
    }
    foreach ($habits->take(2) as $habit) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => today(), 'ai_status' => 'approved']);
    }
    HabitCompletion::factory()->create(['habit_id' => $habits->last()->id, 'completed_on' => today(), 'ai_status' => 'rejected']);
    HabitCompletion::factory()->create(['completed_on' => today(), 'ai_status' => 'approved']);
    $this->actingAs($user)->get('/')->assertOk()->assertViewHas('progressPercent', 67)->assertViewHas('streak', 0);
    $this->get('/progress')->assertOk()->assertViewHas('weeklyPercent', 52)->assertViewHas('bestStreak', 3)
        ->assertViewHas('weakHabit', fn ($habit) => $habit->id === $habits->last()->id)
        ->assertViewHas('chartDays', fn ($days) => count($days) === 7 && $days[6]['percent'] === 67);
    $this->get('/history')->assertOk()->assertViewHas('days', fn ($days) => count($days) === 30 && $days[0]['done'] === 2 && $days[1]['perfect']);
    $this->patch('/habits/'.$habits->last()->id, ['is_daily' => 0]);
    $this->get('/')->assertViewHas('streak', 4);
    $this->get('/progress')->assertViewHas('weakHabit', fn ($habit) => $habit->id === $habits->first()->id);
});

test('aggregates preserve zero counts, scope and inclusive range boundaries', function () {
    $user = User::factory()->create();
    $habits = Habit::factory()->count(2)->create(['user_id' => $user->id]);
    foreach ([0, 6, 7] as $days) {
        HabitCompletion::factory()->create(['habit_id' => $habits[0]->id, 'completed_on' => today()->subDays($days), 'ai_status' => 'approved']);
    }
    $metrics = app(HabitMetrics::class);
    $ids = $habits->pluck('id')->all();
    expect($metrics->completionCountsByHabit($user, $ids, today()->subDays(6), today()))->toBe([$ids[0] => 2, $ids[1] => 0]);
    $dates = $metrics->completionCountsByDate($user, $ids, today()->subDays(6), today());
    expect($dates)->toHaveCount(2);
    expect($dates[today()->toDateString()])->toBe(1);
    expect($metrics->completionCountsByDate(User::factory()->create(), $ids, today()->subDays(6), today()))->toBe([]);
});

test('empty reports render with zero totals', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertViewHas('progressPercent', 0)->assertViewHas('streak', 0);
    $this->get('/progress')->assertOk()->assertViewHas('weeklyPercent', 0)->assertViewHas('bestStreak', 0)->assertViewHas('weakHabit', null);
    $this->get('/history')->assertOk()->assertViewHas('days', fn ($days) => count($days) === 30 && $days[0]['total'] === 0);
});
