<?php

namespace Tests\Feature;

use App\Models\EvidenceSubmission;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountabilityReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone' => 'Asia/Karachi']);
    }

    public function test_accountability_summary_aggregates_metrics(): void
    {
        $routine = Routine::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'Spiritual',
            'estimated_duration' => 20,
        ]);

        $step = RoutineStep::create([
            'routine_id' => $routine->id,
            'title' => 'Fajr Salah',
            'sort_order' => 1,
        ]);

        $now = Carbon::now('UTC');

        // 1. Completed verified occurrence
        $occ1 = TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => $now->copy()->subDays(2),
            'started_at' => $now->copy()->subDays(2)->addMinutes(5),
            'completed_at' => $now->copy()->subDays(2)->addMinutes(25),
            'status' => TaskOccurrence::STATUS_COMPLETED,
            'actual_duration' => 1200,
        ]);

        EvidenceSubmission::create([
            'user_id' => $this->user->id,
            'occurrence_id' => $occ1->id,
            'routine_step_id' => $step->id,
            'evidence_type' => 'camera',
            'verification_status' => EvidenceSubmission::STATUS_RULE_SATISFIED,
            'validation_source' => 'camera_capture',
            'submitted_at' => $now->copy()->subDays(2),
        ]);

        // 2. Completed self-reported occurrence
        TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => $now->copy()->subDays(1),
            'started_at' => $now->copy()->subDays(1)->addMinutes(2),
            'completed_at' => $now->copy()->subDays(1)->addMinutes(20),
            'status' => TaskOccurrence::STATUS_COMPLETED,
            'actual_duration' => 1080,
        ]);

        // 3. Missed occurrence
        TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => $now->copy()->subHours(10),
            'status' => TaskOccurrence::STATUS_MISSED,
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/v1/accountability/summary');

        $res->assertStatus(200)
            ->assertJsonPath('metrics.completed_routines', 2)
            ->assertJsonPath('metrics.missed_routines', 1)
            ->assertJsonPath('metrics.verified_completions', 1)
            ->assertJsonPath('metrics.self_reported_completions', 1)
            ->assertJsonPath('category_breakdown.0.category', 'Spiritual');
    }

    public function test_accountability_history_returns_chronological_records(): void
    {
        $routine = Routine::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Morning Routine',
            'category' => 'Health',
        ]);

        TaskOccurrence::create([
            'routine_id' => $routine->id,
            'due_at' => Carbon::now('UTC')->subDay(),
            'status' => TaskOccurrence::STATUS_COMPLETED,
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/v1/accountability/history');

        $res->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('history.0.routine_title', 'Morning Routine');
    }
}
