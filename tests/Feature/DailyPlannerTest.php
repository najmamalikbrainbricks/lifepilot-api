<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Models\TaskOccurrence;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_planner_returns_unified_timeline(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        // Create routine
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Client Design Review',
            'category' => 'Work',
            'estimated_duration' => 45,
            'enabled' => true,
        ]);
        $routine->schedules()->create([
            'recurrence_type' => 'daily',
            'local_start_time' => '11:00',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        // Create work shift
        WorkShift::create([
            'user_id' => $user->id,
            'job_name' => 'Agency Work Shift',
            'start_time' => '19:00',
            'end_time' => '01:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6],
            'overnight' => true,
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/planner/daily?date=2026-11-21');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'timezone',
                'timeline_blocks',
                'conflicts',
                'free_slots',
                'prayer_summary',
                'stats',
            ]);

        $blocks = collect($response->json('timeline_blocks'));

        // Should contain prayer blocks
        $this->assertTrue($blocks->contains('type', 'prayer'));

        // Should contain work shift
        $this->assertTrue($blocks->contains('type', 'work_shift'));

        // Should contain routine occurrence
        $this->assertTrue($blocks->contains('type', 'routine_occurrence'));
    }

    public function test_conflict_detection_identifies_overlaps(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        // Fixed work shift from 14:00 to 18:00 on Saturday (day 6, 2026-11-21 is Sat)
        WorkShift::create([
            'user_id' => $user->id,
            'job_name' => 'Support Desk',
            'start_time' => '14:00',
            'end_time' => '18:00',
            'days_of_week' => [6],
            'fixed_or_flexible' => 'fixed',
            'enabled' => true,
        ]);

        // Routine scheduled right inside work shift at 15:00 for 60m
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Personal Errand',
            'category' => 'Personal',
            'estimated_duration' => 60,
            'enabled' => true,
        ]);
        $schedule = $routine->schedules()->create([
            'recurrence_type' => 'daily',
            'local_start_time' => '15:00',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/planner/conflicts?date=2026-11-21');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $response->json('conflicts_count'));

        $conflicts = collect($response->json('conflicts'));
        $this->assertTrue($conflicts->contains('type', 'work_conflict'));
    }

    public function test_can_suggest_available_free_slots(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        $response = $this->actingAs($user)->postJson('/api/v1/planner/suggestions', [
            'duration_minutes' => 45,
            'date' => '2026-11-21',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'required_duration_minutes',
                'suggestions_count',
                'suggestions' => [
                    '*' => ['suggested_start_time', 'suggested_end_time', 'slot_duration_minutes', 'rationale'],
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('suggestions_count'));
    }

    public function test_can_reschedule_individual_occurrence_without_modifying_schedule(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Evening Study',
            'category' => 'Study',
            'enabled' => true,
        ]);
        $schedule = $routine->schedules()->create([
            'recurrence_type' => 'daily',
            'local_start_time' => '17:00',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        $origDueAt = Carbon::parse('2026-11-21 17:00:00', 'Asia/Karachi');
        $occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'schedule_id' => $schedule->id,
            'due_at' => $origDueAt->copy()->utc(),
            'status' => 'scheduled',
        ]);

        // Reschedule occurrence to 20:30
        $newDueAt = Carbon::parse('2026-11-21 20:30:00', 'Asia/Karachi');

        $response = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/reschedule", [
            'new_due_at' => $newDueAt->toIso8601String(),
            'reason' => 'Delay due to urgent client email',
        ]);

        $response->assertStatus(200);

        $fresh = $occurrence->fresh();
        $this->assertTrue($fresh->is_rescheduled);
        $this->assertEquals('Delay due to urgent client email', $fresh->reschedule_reason);
        $this->assertEquals($newDueAt->utc()->toDateTimeString(), $fresh->due_at->toDateTimeString());
        $this->assertEquals($origDueAt->utc()->toDateTimeString(), $fresh->original_due_at->toDateTimeString());

        // Recurring schedule MUST NOT have changed!
        $this->assertEquals('17:00', $schedule->fresh()->local_start_time);

        // Reset reschedule
        $resetRes = $this->actingAs($user)->postJson("/api/v1/occurrences/{$occurrence->id}/reset-reschedule");
        $resetRes->assertStatus(200);
        $this->assertFalse($occurrence->fresh()->is_rescheduled);
        $this->assertEquals($origDueAt->utc()->toDateTimeString(), $occurrence->fresh()->due_at->toDateTimeString());
    }

    public function test_user_cannot_reschedule_another_users_occurrence(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $routine = Routine::create(['user_id' => $user1->id, 'title' => 'Routine 1', 'category' => 'Personal']);
        $occurrence = TaskOccurrence::create(['routine_id' => $routine->id, 'due_at' => Carbon::now()]);

        $response = $this->actingAs($user2)->postJson("/api/v1/occurrences/{$occurrence->id}/reschedule", [
            'new_due_at' => Carbon::now()->addHour()->toIso8601String(),
        ]);

        $response->assertStatus(403);
    }

    public function test_weekly_and_monthly_calendar_endpoints(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        $weekRes = $this->actingAs($user)->getJson('/api/v1/planner/weekly?date=2026-11-21');
        $weekRes->assertStatus(200)
            ->assertJsonStructure(['week_start', 'week_end', 'days'])
            ->assertJsonCount(7, 'days');

        $monthRes = $this->actingAs($user)->getJson('/api/v1/planner/monthly?month=11&year=2026');
        $monthRes->assertStatus(200)
            ->assertJsonStructure(['year', 'month', 'days'])
            ->assertJsonCount(30, 'days'); // November has 30 days
    }
}
