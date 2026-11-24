<?php

namespace Tests\Feature;

use App\Models\AlarmEvent;
use App\Models\AlarmRule;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlarmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $this->otherUser = User::factory()->create(['timezone' => 'Asia/Karachi']);
    }

    public function test_due_alarms_returns_tasks_due_now(): void
    {
        $routine = Routine::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Morning Fajr Routine',
            'enabled' => true,
        ]);

        AlarmRule::create([
            'routine_id' => $routine->id,
            'enabled' => true,
            'sound' => 'flowing_water',
            'repeat_interval' => 5,
            'max_attempts' => 3,
            'snooze_duration' => 5,
        ]);

        $now = Carbon::now('UTC');

        // Due 5 minutes ago -> should be returned
        $dueOcc = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => $now->copy()->subMinutes(5),
            'status' => TaskOccurrence::STATUS_SCHEDULED,
        ]);

        // Due 3 hours in future -> should not be returned
        TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => $now->copy()->addHours(3),
            'status' => TaskOccurrence::STATUS_SCHEDULED,
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/v1/alarms/due');

        $res->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('due_alarms.0.id', $dueOcc->id);
    }

    public function test_trigger_alarm_sets_ringing_status_and_logs_event(): void
    {
        $routine = Routine::factory()->create(['user_id' => $this->user->id]);
        $alarmRule = AlarmRule::create([
            'routine_id' => $routine->id,
            'enabled' => true,
            'sound' => 'gentle_chime',
        ]);

        $occ = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC'),
            'status' => TaskOccurrence::STATUS_SCHEDULED,
        ]);

        $res = $this->actingAs($this->user)->postJson("/api/v1/alarms/{$occ->id}/trigger");

        $res->assertStatus(200)
            ->assertJsonPath('occurrence.status', TaskOccurrence::STATUS_RINGING);

        $this->assertDatabaseHas('alarm_events', [
            'user_id' => $this->user->id,
            'occurrence_id' => $occ->id,
            'event_type' => AlarmEvent::EVENT_RINGING,
        ]);

        $this->assertEquals(1, $occ->fresh()->alarm_attempts);
    }

    public function test_acknowledge_alarm_transitions_state_and_records_duration(): void
    {
        $routine = Routine::factory()->create(['user_id' => $this->user->id]);
        $occ = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC')->subMinutes(2),
            'status' => TaskOccurrence::STATUS_RINGING,
            'ringing_started_at' => Carbon::now('UTC')->subSeconds(30),
            'alarm_attempts' => 1,
        ]);

        $res = $this->actingAs($this->user)->postJson("/api/v1/alarms/{$occ->id}/acknowledge");

        $res->assertStatus(200)
            ->assertJsonPath('occurrence.status', TaskOccurrence::STATUS_ACKNOWLEDGED);

        $this->assertDatabaseHas('alarm_events', [
            'user_id' => $this->user->id,
            'occurrence_id' => $occ->id,
            'event_type' => AlarmEvent::EVENT_ACKNOWLEDGED,
        ]);

        $this->assertNotNull($occ->fresh()->acknowledged_at);
    }

    public function test_snooze_alarm_sets_snoozed_until_and_handles_max_attempts(): void
    {
        $routine = Routine::factory()->create(['user_id' => $this->user->id]);
        AlarmRule::create([
            'routine_id' => $routine->id,
            'enabled' => true,
            'max_attempts' => 2,
            'snooze_duration' => 10,
        ]);

        $occ = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC')->subMinutes(5),
            'status' => TaskOccurrence::STATUS_RINGING,
            'alarm_attempts' => 1,
        ]);

        // First snooze -> status becomes snoozed
        $res = $this->actingAs($this->user)->postJson("/api/v1/alarms/{$occ->id}/snooze", ['minutes' => 10]);
        $res->assertStatus(200)
            ->assertJsonPath('occurrence.status', TaskOccurrence::STATUS_SNOOZED);

        $this->assertNotNull($occ->fresh()->snoozed_until);

        // Advance attempts to max and snooze again -> auto-marked as missed
        $occ->alarm_attempts = 2;
        $occ->save();

        $res2 = $this->actingAs($this->user)->postJson("/api/v1/alarms/{$occ->id}/snooze");
        $res2->assertStatus(200)
            ->assertJsonPath('occurrence.status', TaskOccurrence::STATUS_MISSED);
    }

    public function test_emergency_stop_silences_active_alarms(): void
    {
        $routine = Routine::factory()->create(['user_id' => $this->user->id]);
        TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC'),
            'status' => TaskOccurrence::STATUS_RINGING,
        ]);

        $res = $this->actingAs($this->user)->postJson('/api/v1/alarms/emergency-stop');

        $res->assertStatus(200)
            ->assertJsonPath('stopped_count', 1);

        $this->assertDatabaseHas('alarm_events', [
            'user_id' => $this->user->id,
            'event_type' => AlarmEvent::EVENT_EMERGENCY_STOP,
        ]);
    }

    public function test_user_cannot_acknowledge_another_users_alarm(): void
    {
        $routine = Routine::factory()->create(['user_id' => $this->otherUser->id]);
        $occ = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC'),
            'status' => TaskOccurrence::STATUS_RINGING,
        ]);

        $res = $this->actingAs($this->user)->postJson("/api/v1/alarms/{$occ->id}/acknowledge");
        $res->assertStatus(403);
    }
}
