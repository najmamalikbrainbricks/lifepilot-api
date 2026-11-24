<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Routine;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_user_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/preferences', [
            'theme' => 'light',
            'time_format' => '24h',
            'sleep_goal' => 7.5,
            'bedtime' => '02:00',
            'wake_time' => '10:00',
            'focus_goal' => 90,
            'focus_category' => 'Deep Work',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('preference.theme', 'light')
            ->assertJsonPath('preference.time_format', '24h')
            ->assertJsonPath('preference.sleep_goal', 7.5)
            ->assertJsonPath('preference.focus_goal', 90);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'theme' => 'light',
            'focus_goal' => 90,
        ]);
    }

    public function test_can_manage_work_shifts_with_overnight_detection(): void
    {
        $user = User::factory()->create();

        // Overnight shift: 19:00 to 01:00
        $response = $this->actingAs($user)->postJson('/api/v1/work-shifts', [
            'job_name' => 'Agency Night Shift',
            'start_time' => '19:00',
            'end_time' => '01:00',
            'days_of_week' => [1, 2, 3, 4, 5],
            'fixed_or_flexible' => 'fixed',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('work_shift.job_name', 'Agency Night Shift')
            ->assertJsonPath('work_shift.overnight', true);

        $shiftId = $response->json('work_shift.id');

        // Delete shift
        $del = $this->actingAs($user)->deleteJson("/api/v1/work-shifts/{$shiftId}");
        $del->assertStatus(200);
        $this->assertDatabaseMissing('work_shifts', ['id' => $shiftId]);
    }

    public function test_can_manage_goals(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/goals', [
            'title' => 'Complete Quran reading',
            'category' => 'Spiritual',
            'priority' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('goal.title', 'Complete Quran reading');

        $goalId = $response->json('goal.id');

        $update = $this->actingAs($user)->putJson("/api/v1/goals/{$goalId}", [
            'title' => 'Complete Quran reading daily',
            'active' => true,
        ]);
        $update->assertStatus(200)
            ->assertJsonPath('goal.title', 'Complete Quran reading daily');
    }

    public function test_daily_report_endpoint_returns_real_summary(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Daily Task',
            'category' => 'Work',
            'enabled' => true,
        ]);

        $routine->schedules()->create([
            'recurrence_type' => 'daily',
            'local_start_time' => '10:00',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/reports/daily');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'date_formatted',
                'timezone',
                'summary' => [
                    'total_routines',
                    'completed',
                    'pending',
                    'missed',
                    'completion_percent',
                    'focus_goal_minutes',
                ],
                'occurrences',
            ]);
    }

    public function test_browser_data_migration_imports_data_safely(): void
    {
        $user = User::factory()->create();

        $payload = [
            'settings' => [
                'name' => 'Alex Prototype',
                'timezone' => 'Asia/Karachi',
                'theme' => 'dark',
                'sleepGoalHours' => 8,
                'focusGoalMinutes' => 75,
                'goals' => ['Agency growth', 'Healthy eating'],
                'shifts' => [
                    [
                        'name' => 'Client Consulting',
                        'start' => '18:00',
                        'end' => '22:00',
                        'days' => [1, 2, 3],
                        'fixed' => true,
                    ],
                ],
            ],
            'tasks' => [
                [
                    'id' => 'browser-task-1',
                    'title' => 'Morning stretch',
                    'time' => '7:00 AM',
                    'durationMinutes' => 15,
                    'category' => 'Wellness',
                ],
                [
                    'id' => 'browser-task-2',
                    'title' => 'Deep Focus',
                    'time' => '9:00 AM',
                    'durationMinutes' => 90,
                    'category' => 'Work',
                ],
            ],
        ];

        // First import
        $res = $this->actingAs($user)->postJson('/api/v1/migration/import', $payload);

        $res->assertStatus(200)
            ->assertJsonPath('summary.imported_routines', 2)
            ->assertJsonPath('summary.imported_goals', 2)
            ->assertJsonPath('summary.imported_shifts', 1)
            ->assertJsonPath('summary.settings_updated', true);

        $this->assertDatabaseHas('routines', ['user_id' => $user->id, 'title' => 'Morning stretch']);
        $this->assertDatabaseHas('work_shifts', ['user_id' => $user->id, 'job_name' => 'Client Consulting']);

        // Second import (idempotent / skips existing)
        $res2 = $this->actingAs($user)->postJson('/api/v1/migration/import', $payload);

        $res2->assertStatus(200)
            ->assertJsonPath('summary.imported_routines', 0)
            ->assertJsonPath('summary.skipped_routines', 2)
            ->assertJsonPath('summary.skipped_goals', 2)
            ->assertJsonPath('summary.skipped_shifts', 1);
    }
}
