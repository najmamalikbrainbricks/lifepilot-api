<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected TaskOccurrence $occurrence;
    protected RoutineStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $routine = Routine::factory()->create(['user_id' => $this->user->id]);
        $this->step = RoutineStep::create([
            'routine_id' => $routine->id,
            'title' => 'Deep Work',
            'sort_order' => 1,
        ]);
        $this->occurrence = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC'),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_user_can_start_pause_resume_and_complete_timer(): void
    {
        // 1. Start timer session
        $startRes = $this->actingAs($this->user)->postJson('/api/v1/timer-sessions', [
            'occurrence_id' => $this->occurrence->id,
            'routine_step_id' => $this->step->id,
            'target_duration_seconds' => 1800,
        ]);

        $startRes->assertStatus(201)
            ->assertJsonPath('timer_session.status', TimerSession::STATUS_RUNNING);

        $sessionId = $startRes->json('timer_session.id');

        // 2. Pause session
        $pauseRes = $this->actingAs($this->user)->postJson("/api/v1/timer-sessions/{$sessionId}/pause");
        $pauseRes->assertStatus(200)
            ->assertJsonPath('timer_session.status', TimerSession::STATUS_PAUSED);

        // 3. Resume session
        $resumeRes = $this->actingAs($this->user)->postJson("/api/v1/timer-sessions/{$sessionId}/resume");
        $resumeRes->assertStatus(200)
            ->assertJsonPath('timer_session.status', TimerSession::STATUS_RUNNING);

        // 4. Complete session
        $completeRes = $this->actingAs($this->user)->postJson("/api/v1/timer-sessions/{$sessionId}/complete");
        $completeRes->assertStatus(200)
            ->assertJsonPath('timer_session.status', TimerSession::STATUS_COMPLETED);

        $this->assertDatabaseHas('timer_sessions', [
            'id' => $sessionId,
            'status' => TimerSession::STATUS_COMPLETED,
        ]);
    }

    public function test_user_cannot_pause_another_users_timer(): void
    {
        $session = TimerSession::create([
            'user_id' => $this->otherUser->id,
            'occurrence_id' => $this->occurrence->id,
            'started_at' => Carbon::now('UTC'),
            'status' => TimerSession::STATUS_RUNNING,
        ]);

        $res = $this->actingAs($this->user)->postJson("/api/v1/timer-sessions/{$session->id}/pause");
        $res->assertStatus(403);
    }
}
