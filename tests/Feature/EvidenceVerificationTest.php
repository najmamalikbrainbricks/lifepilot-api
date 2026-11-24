<?php

namespace Tests\Feature;

use App\Models\EvidenceSubmission;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\StepCompletion;
use App\Models\StepVerificationRule;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Routine $routine;
    protected RoutineStep $step;
    protected TaskOccurrence $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $this->otherUser = User::factory()->create(['timezone' => 'Asia/Karachi']);

        $this->routine = Routine::factory()->create(['user_id' => $this->user->id]);
        $this->step = RoutineStep::create([
            'routine_id' => $this->routine->id,
            'title' => 'Perform Wudu',
            'sort_order' => 1,
            'required' => true,
            'verification_type' => 'camera',
        ]);

        $this->occurrence = TaskOccurrence::create([
            'routine_id' => $this->routine->id,
            'due_at' => Carbon::now('UTC'),
            'status' => TaskOccurrence::STATUS_IN_PROGRESS,
        ]);
    }

    public function test_submit_valid_image_evidence(): void
    {
        $file = UploadedFile::fake()->image('wudu_check.jpg', 640, 480);

        $res = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$this->step->id}/evidence",
            [
                'image' => $file,
                'source' => 'camera_capture',
            ]
        );

        $res->assertStatus(201)
            ->assertJsonPath('evidence.verification_status', EvidenceSubmission::STATUS_RULE_SATISFIED)
            ->assertJsonPath('evidence.evidence_type', EvidenceSubmission::TYPE_CAMERA);

        $this->assertDatabaseHas('evidence_submissions', [
            'user_id' => $this->user->id,
            'occurrence_id' => $this->occurrence->id,
            'routine_step_id' => $this->step->id,
            'verification_status' => EvidenceSubmission::STATUS_RULE_SATISFIED,
        ]);

        // Verify StepCompletion was automatically completed
        $this->assertDatabaseHas('step_completions', [
            'occurrence_id' => $this->occurrence->id,
            'routine_step_id' => $this->step->id,
            'status' => StepCompletion::STATUS_COMPLETED,
        ]);
    }

    public function test_reject_unsupported_file_mime_type(): void
    {
        $file = UploadedFile::fake()->create('script.php', 100, 'application/x-php');

        $res = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$this->step->id}/evidence",
            [
                'image' => $file,
            ]
        );

        $res->assertStatus(422)
            ->assertJsonFragment(['message' => 'Unsupported image format (application/x-php). Only JPEG, PNG, and WebP are accepted.']);
    }

    public function test_reject_oversized_image_file(): void
    {
        // 6MB image (max is 5MB)
        $file = UploadedFile::fake()->image('huge.jpg')->size(6000);

        $res = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$this->step->id}/evidence",
            [
                'image' => $file,
            ]
        );

        $res->assertStatus(422)
            ->assertJsonFragment(['message' => 'Image file exceeds the 5MB maximum allowed size']);
    }

    public function test_submit_structured_timer_verification(): void
    {
        $timerStep = RoutineStep::create([
            'routine_id' => $this->routine->id,
            'title' => 'Deep Work Session',
            'sort_order' => 2,
            'verification_type' => 'timer',
        ]);

        StepVerificationRule::create([
            'routine_step_id' => $timerStep->id,
            'verification_type' => 'timer',
            'minimum_duration' => 60, // 60s minimum
        ]);

        // Fails if elapsed duration less than minimum
        $failRes = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$timerStep->id}/evidence",
            [
                'evidence_type' => 'timer',
                'data' => ['elapsed_seconds' => 30],
            ]
        );
        $failRes->assertStatus(422);

        // Succeeds if elapsed duration meets minimum
        $passRes = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$timerStep->id}/evidence",
            [
                'evidence_type' => 'timer',
                'data' => ['elapsed_seconds' => 65],
            ]
        );
        $passRes->assertStatus(201)
            ->assertJsonPath('evidence.validation_source', 'timer_elapsed');
    }

    public function test_authenticated_user_can_view_own_evidence_file(): void
    {
        $file = UploadedFile::fake()->image('proof.jpg', 400, 300);
        $res = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$this->step->id}/evidence",
            ['image' => $file]
        );

        $evidenceId = $res->json('evidence.id');

        // Owner can access file
        $fileRes = $this->actingAs($this->user)->get("/api/v1/evidence/{$evidenceId}");
        $fileRes->assertStatus(200);

        // Other user is forbidden
        $forbiddenRes = $this->actingAs($this->otherUser)->get("/api/v1/evidence/{$evidenceId}");
        $forbiddenRes->assertStatus(403);
    }

    public function test_owner_can_delete_evidence(): void
    {
        $file = UploadedFile::fake()->image('temp.jpg', 200, 200);
        $res = $this->actingAs($this->user)->postJson(
            "/api/v1/occurrences/{$this->occurrence->id}/steps/{$this->step->id}/evidence",
            ['image' => $file]
        );

        $evidenceId = $res->json('evidence.id');

        $deleteRes = $this->actingAs($this->user)->deleteJson("/api/v1/evidence/{$evidenceId}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('evidence_submissions', ['id' => $evidenceId]);
    }
}
