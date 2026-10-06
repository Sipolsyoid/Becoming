<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Services\HabitMetrics;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'UTC'));
});

test('new habits only enter statistics on their local creation date', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-06', 'ai_status' => 'approved']);
    $this->actingAs($user)->get('/progress')->assertViewHas('weeklyPercent', 100)
        ->assertViewHas('chartDays', fn ($days) => array_sum(array_column($days, 'total')) === 1);
    $this->get('/history')->assertViewHas('days', fn ($days) => $days[0]['total'] === 1 && $days[1]['total'] === 0);
});

test('creation boundary uses local date and stays stable after changing timezone', function () {
    $this->travelTo(Carbon::parse('2026-10-05 22:30:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'Europe/Riga']);
    $habit = Habit::factory()->create(['user_id' => $user->id]);
    expect($habit->isDueOn(Carbon::parse('2026-10-05')))->toBeFalse();
    expect($habit->isDueOn(Carbon::parse('2026-10-06')))->toBeTrue();
    $user->update(['timezone' => 'America/Los_Angeles']);
    expect($habit->fresh()->isDueOn(Carbon::parse('2026-10-05')))->toBeFalse();
});

test('today extends a streak only once complete and breaks it only after midnight', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'created_at' => '2026-10-04 00:00:00']);
    foreach (['2026-10-04', '2026-10-05'] as $date) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => $date, 'ai_status' => 'approved']);
    }
    $this->actingAs($user)->get('/')->assertViewHas('streak', 2);
    $this->get('/progress')->assertViewHas('bestStreak', 2);
    $this->travelTo(Carbon::parse('2026-10-07 00:00:00', 'UTC'));
    $this->get('/')->assertViewHas('streak', 0);
    // A delayed background approval for yesterday repairs the historical streak.
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-06', 'ai_status' => 'approved']);
    $this->get('/')->assertViewHas('streak', 3);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-07', 'ai_status' => 'approved']);
    $this->get('/')->assertViewHas('streak', 4);
});

test('schedule changes preserve earlier daily denominators including same day edits', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'created_at' => '2026-10-04 00:00:00']);
    $this->actingAs($user)->patch('/habits/'.$habit->id, ['schedule_type' => 'weekdays', 'weekdays' => [3]])->assertSessionHasNoErrors();
    $this->patch('/habits/'.$habit->id, ['schedule_type' => 'weekdays', 'weekdays' => [4]])->assertSessionHasNoErrors();
    expect($habit->scheduleVersions()->count())->toBe(2);
    $this->get('/history')->assertViewHas('days', fn ($days) => $days[0]['total'] === 0 && $days[1]['total'] === 1 && $days[2]['total'] === 1 && $days[3]['total'] === 0);
    $habit = $habit->fresh();
    expect($habit->isDueOn(Carbon::parse('2026-10-05')))->toBeTrue();
    expect($habit->isDueOn(Carbon::parse('2026-10-07')))->toBeFalse();
    expect($habit->isDueOn(Carbon::parse('2026-10-08')))->toBeTrue();
});

test('pausing and resuming retain earlier history without filling paused days', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'created_at' => '2026-10-05 00:00:00']);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
    $this->actingAs($user)->patch('/habits/'.$habit->id, ['is_daily' => 0]);
    $this->get('/')->assertViewHas('streak', 1)->assertViewHas('totalCount', 0);
    $this->travelTo(Carbon::parse('2026-10-08 12:00:00', 'UTC'));
    $this->patch('/habits/'.$habit->id, ['is_daily' => 1]);
    $this->get('/history')->assertViewHas('days', fn ($days) => array_column(array_slice($days, 0, 4), 'total') === [1, 0, 0, 1]);
    $this->get('/')->assertViewHas('streak', 1);
});

test('a direct model update records a new effective date without altering creation snapshot', function () {
    $habit = Habit::factory()->create();
    $this->travel(1)->days();
    $habit->update(['schedule_type' => 'weekly', 'weekly_target' => 2]);
    expect($habit->scheduleVersions()->count())->toBe(2);
    expect($habit->isDueOn(Carbon::parse('2026-10-06')))->toBeTrue();
    expect($habit->isDueOn(Carbon::parse('2026-10-07')))->toBeFalse();
});

test('weekly history keeps past targets and includes currently paused habits', function () {
    $this->travelTo(Carbon::parse('2026-09-28 12:00:00', 'UTC'));
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 2]);
    foreach (['2026-09-28', '2026-09-29'] as $date) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => $date, 'ai_status' => 'approved']);
    }
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
    $this->actingAs($user)->patch('/habits/'.$habit->id, ['schedule_type' => 'weekly', 'weekly_target' => 4]);
    $this->travel(1)->days();
    $this->patch('/habits/'.$habit->id, ['is_daily' => 0]);
    $history = app(HabitMetrics::class)->weeklyHistory($user, $user->localToday());
    expect($history[0]['goals']->sole()['target'])->toBe(4);
    expect($history[1]['goals']->sole())->toMatchArray(['done' => 2, 'target' => 2, 'reached' => true]);
    $this->get('/history')->assertOk()->assertSee('Weekly goal history')->assertSee('Goal reached');
});

test('daily approvals do not count after switching to a weekly goal midweek', function () {
    $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
    $user = User::factory()->create();
    $habit = Habit::factory()->create(['user_id' => $user->id]);
    HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-05', 'ai_status' => 'approved']);
    $this->travel(1)->days();
    $habit->update(['schedule_type' => 'weekly', 'weekly_target' => 3]);
    $goal = app(HabitMetrics::class)->weeklyGoals($user, $user->localToday())->sole();
    expect($goal)->toMatchArray(['done' => 0, 'target' => 3, 'partial' => true]);
    $this->actingAs($user)->get('/history')->assertViewHas('days', fn ($days) => $days[1]['done'] === 1 && $days[1]['total'] === 1);
});

test('weekly history is scoped to the owner and excludes rejected approvals', function () {
    $user = User::factory()->create();
    $own = Habit::factory()->create(['user_id' => $user->id, 'schedule_type' => 'weekly', 'weekly_target' => 1]);
    $other = Habit::factory()->create(['schedule_type' => 'weekly', 'weekly_target' => 1]);
    foreach ([$own, $other] as $habit) {
        HabitCompletion::factory()->create(['habit_id' => $habit->id, 'completed_on' => '2026-10-06', 'ai_status' => 'rejected']);
    }
    $goals = app(HabitMetrics::class)->weeklyHistory($user, $user->localToday())[0]['goals'];
    expect($goals->count())->toBe(1);
    expect($goals->sole()['habit']->id)->toBe($own->id);
    expect($goals->sole()['done'])->toBe(0);
});
