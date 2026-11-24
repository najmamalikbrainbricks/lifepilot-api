<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_routine_with_steps_schedule_and_alarm_rule(): void
    {
        $user = User::factory()->create();

        $payload = [
            'title' => 'Morning Wudu & Prayer',
            'description' => 'Mindful preparation and Fajr',
            'category' => 'Spiritual',
            'priority' => 'high',
            'estimated_duration' => 20,
            'icon' => 'Waves',
            'color' => '#0ea5e9',
            'steps' => [
                [
                    'title' => 'Perform Wudu',
                    'description' => 'Wash with presence',
                    'sort_order' => 0,
                    'estimated_duration' => 5,
                    'required' => true,
                    'verification_type' => 'self',
                ],
                [
                    'title' => 'Pray Fajr',
                    'description' => '2 Sunnah + 2 Fard',
                    'sort_order' => 1,
                    'estimated_duration' => 10,
                    'required' => true,
                    'verification_type' => 'self',
                ],
                [
                    'title' => 'Morning Dua & Dhikr',
                    'description' => 'Gratitude and intention',
                    'sort_order' => 2,
                    'estimated_duration' => 5,
                    'required' => false,
                    'verification_type' => 'self',
                ],
            ],
            'schedule' => [
                'recurrence_type' => 'daily',
                'local_start_time' => '05:30',
                'timezone' => 'Asia/Karachi',
            ],
            'alarm_rule' => [
                'enabled' => true,
                'sound' => 'flowing_water',
                'repeat_interval' => 5,
                'max_attempts' => 3,
                'snooze_duration' => 5,
                'verification_required' => false,
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/routines', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('routine.title', 'Morning Wudu & Prayer')
            ->assertJsonPath('routine.category', 'Spiritual')
            ->assertJsonCount(3, 'routine.steps')
            ->assertJsonPath('routine.alarm_rule.sound', 'flowing_water');

        $this->assertDatabaseHas('routines', ['user_id' => $user->id, 'title' => 'Morning Wudu & Prayer']);
        $this->assertDatabaseHas('routine_steps', ['title' => 'Perform Wudu', 'required' => 1]);
        $this->assertDatabaseHas('routine_schedules', ['recurrence_type' => 'daily', 'local_start_time' => '05:30']);
        $this->assertDatabaseHas('alarm_rules', ['sound' => 'flowing_water']);
    }

    public function test_can_list_and_filter_routines(): void
    {
        $user = User::factory()->create();

        $routine1 = Routine::create([
            'user_id' => $user->id,
            'title' => 'Morning Focus Sprint',
            'category' => 'Work',
            'enabled' => true,
        ]);

        $routine2 = Routine::create([
            'user_id' => $user->id,
            'title' => 'Evening Stretch',
            'category' => 'Health',
            'enabled' => false,
        ]);

        // Filter active
        $resActive = $this->actingAs($user)->getJson('/api/v1/routines?status=active');
        $resActive->assertStatus(200)
            ->assertJsonCount(1, 'routines')
            ->assertJsonPath('routines.0.title', 'Morning Focus Sprint');

        // Filter paused
        $resPaused = $this->actingAs($user)->getJson('/api/v1/routines?status=paused');
        $resPaused->assertStatus(200)
            ->assertJsonCount(1, 'routines')
            ->assertJsonPath('routines.0.title', 'Evening Stretch');

        // Search filter
        $resSearch = $this->actingAs($user)->getJson('/api/v1/routines?search=Sprint');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'routines');
    }

    public function test_can_archive_and_unarchive_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Old Habit',
            'category' => 'Personal',
            'enabled' => true,
        ]);

        $resArchive = $this->actingAs($user)->postJson("/api/v1/routines/{$routine->id}/archive");
        $resArchive->assertStatus(200);
        $this->assertNotNull($routine->fresh()->archived_at);

        $resUnarchive = $this->actingAs($user)->postJson("/api/v1/routines/{$routine->id}/unarchive");
        $resUnarchive->assertStatus(200);
        $this->assertNull($routine->fresh()->archived_at);
    }

    public function test_can_reorder_routine_steps(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Step Order Routine',
            'category' => 'Personal',
        ]);

        $s1 = $routine->steps()->create(['title' => 'First', 'sort_order' => 0]);
        $s2 = $routine->steps()->create(['title' => 'Second', 'sort_order' => 1]);

        $response = $this->actingAs($user)->postJson("/api/v1/routines/{$routine->id}/steps/reorder", [
            'step_ids' => [$s2->id, $s1->id],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0, $s2->fresh()->sort_order);
        $this->assertEquals(1, $s1->fresh()->sort_order);
    }

    public function test_user_cannot_access_or_modify_other_users_routine(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $routine = Routine::create([
            'user_id' => $user1->id,
            'title' => 'User1 Routine',
            'category' => 'Personal',
        ]);

        $response = $this->actingAs($user2)->getJson("/api/v1/routines/{$routine->id}");
        $response->assertStatus(403);

        $responseDelete = $this->actingAs($user2)->deleteJson("/api/v1/routines/{$routine->id}");
        $responseDelete->assertStatus(403);
    }

    public function test_routine_validation_fails_on_missing_title_or_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/routines', [
            'description' => 'No title or category provided',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category']);
    }
}
