<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Models\TaskOccurrence;
use App\Models\User;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerAndExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_is_idempotent_and_prevents_duplicate_occurrences(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Daily Fajr Routine',
            'category' => 'Spiritual',
            'enabled' => true,
        ]);

        $schedule = RoutineSchedule::create([
            'routine_id' => $routine->id,
            'recurrence_type' => 'daily',
            'local_start_time' => '05:30',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        $service = app(RoutineSchedulerService::class);
        $startDate = Carbon::parse('2026-11-21', 'Asia/Karachi')->startOfDay();
        $endDate = Carbon::parse('2026-11-23', 'Asia/Karachi')->endOfDay();

        // First run
        $firstRun = $service->generateOccurrencesForRoutine($routine, $startDate, $endDate);
        $this->assertCount(3, $firstRun); // 3 days

        // Second run with same dates
        $secondRun = $service->generateOccurrencesForRoutine($routine, $startDate, $endDate);
        $this->assertCount(3, $secondRun);

        // Verify total occurrences in DB is still exactly 3 (no duplicates!)
        $this->assertEquals(3, TaskOccurrence::where('routine_id', $routine->id)->count());
    }

    public function test_selected_weekdays_recurrence(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Gym Routine',
            'category' => 'Fitness',
            'enabled' => true,
        ]);

        // Mon (1), Wed (3), Fri (5)
        $schedule = RoutineSchedule::create([
            'routine_id' => $routine->id,
            'recurrence_type' => 'selected_weekdays',
            'selected_weekdays' => [1, 3, 5],
            'local_start_time' => '07:00',
            'timezone' => 'UTC',
            'enabled' => true,
        ]);

        $service = app(RoutineSchedulerService::class);
        // Week of 2026-11-16 (Monday) to 2026-11-22 (Sunday)
        $monday = Carbon::parse('2026-11-16')->startOfDay();
        $sunday = Carbon::parse('2026-11-22')->endOfDay();

        $occurrences = $service->generateOccurrencesForRoutine($routine, $monday, $sunday);
        $this->assertCount(3, $occurrences); // Mon, Wed, Fri
    }

    public function test_task_occurrence_start_initializes_step_completions(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Multi-step Routine',
            'category' => 'Personal',
        ]);

        $step1 = $routine->steps()->create(['title' => 'Step 1', 'sort_order' => 0, 'required' => true]);
        $step2 = $routine->steps()->create(['title' => 'Step 2', 'sort_order' => 1, 'required' => false]);

        $occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now(),
            'status' => TaskOccurrence::STATUS_SCHEDULED,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/start");

        $response->assertStatus(200)
            ->assertJsonPath('occurrence.status', 'in_progress');

        $this->assertDatabaseHas('task_occurrences', ['id' => $occurrence->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('step_completions', ['occurrence_id' => $occurrence->id, 'routine_step_id' => $step1->id, 'status' => 'pending']);
        $this->assertDatabaseHas('step_completions', ['occurrence_id' => $occurrence->id, 'routine_step_id' => $step2->id, 'status' => 'pending']);
        $this->assertDatabaseHas('activity_events', ['task_occurrence_id' => $occurrence->id, 'event_type' => 'routine_started']);
    }

    public function test_can_complete_step(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create(['user_id' => $user->id, 'title' => 'Study', 'category' => 'Study']);
        $step = $routine->steps()->create(['title' => 'Read chapter 1', 'sort_order' => 0, 'required' => true]);

        $occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now(),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/steps/{$step->id}/complete", [
            'notes' => 'Finished chapter 1 with notes',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('completion.status', 'completed');

        $this->assertDatabaseHas('step_completions', [
            'occurrence_id' => $occurrence->id,
            'routine_step_id' => $step->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('activity_events', ['task_occurrence_id' => $occurrence->id, 'event_type' => 'step_completed']);
    }

    public function test_cannot_skip_required_step_without_override(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create(['user_id' => $user->id, 'title' => 'Prayer', 'category' => 'Spiritual']);
        $step = $routine->steps()->create(['title' => 'Fard prayer', 'sort_order' => 0, 'required' => true]);

        $occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now(),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);

        // Attempt skip without override
        $resFail = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/steps/{$step->id}/skip");
        $resFail->assertStatus(422)
            ->assertJsonPath('requires_override', true);

        // Skip with explicit override
        $resOk = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/steps/{$step->id}/skip", [
            'override' => true,
            'reason' => 'Exceptional circumstance',
        ]);
        $resOk->assertStatus(200)
            ->assertJsonPath('completion.status', 'overridden');
    }

    public function test_cannot_complete_routine_with_uncompleted_required_step_unless_forced(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create(['user_id' => $user->id, 'title' => 'Workout', 'category' => 'Fitness']);
        $step1 = $routine->steps()->create(['title' => 'Warmup', 'sort_order' => 0, 'required' => true]);
        $step2 = $routine->steps()->create(['title' => 'Cooldown', 'sort_order' => 1, 'required' => false]);

        $occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now(),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);

        // Attempt complete while step1 (required) is pending
        $resFail = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/complete");
        $resFail->assertStatus(422)
            ->assertJsonPath('incomplete_required_steps', true);

        // Complete step 1
        $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/steps/{$step1->id}/complete");

        // Now complete routine succeeds
        $resSuccess = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/complete");
        $resSuccess->assertStatus(200)
            ->assertJsonPath('occurrence.status', 'partially_completed'); // step2 was not completed so partially_completed

        // If all steps completed, status is completed
        $occurrence2 = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now()->addHour(),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);
        $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence2->id}/steps/{$step1->id}/complete");
        $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence2->id}/steps/{$step2->id}/complete");

        $resAll = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence2->id}/complete");
        $resAll->assertStatus(200)
            ->assertJsonPath('occurrence.status', 'completed');
    }
}
