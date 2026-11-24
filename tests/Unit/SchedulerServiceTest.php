<?php

namespace Tests\Unit;

use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Models\User;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_occurrence_due_at_is_converted_to_utc_from_local_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Timezone Test',
            'category' => 'Work',
            'enabled' => true,
        ]);

        // Asia/Karachi is UTC+5
        $schedule = RoutineSchedule::create([
            'routine_id' => $routine->id,
            'recurrence_type' => 'daily',
            'local_start_time' => '10:00',
            'timezone' => 'Asia/Karachi',
            'enabled' => true,
        ]);

        $service = app(RoutineSchedulerService::class);
        $date = Carbon::parse('2026-11-21', 'Asia/Karachi')->startOfDay();

        $occurrences = $service->generateForSchedule($schedule, $date, $date);

        $this->assertCount(1, $occurrences);
        // 10:00 AM Asia/Karachi is 05:00 AM UTC
        $this->assertEquals('2026-11-21 05:00:00', $occurrences->first()->due_at->format('Y-m-d H:i:s'));
    }

    public function test_inactive_schedule_does_not_generate_occurrences(): void
    {
        $user = User::factory()->create();
        $routine = Routine::create([
            'user_id' => $user->id,
            'title' => 'Inactive Test',
            'category' => 'Work',
            'enabled' => false,
        ]);

        $schedule = RoutineSchedule::create([
            'routine_id' => $routine->id,
            'recurrence_type' => 'daily',
            'local_start_time' => '10:00',
            'enabled' => true,
        ]);

        $service = app(RoutineSchedulerService::class);
        $date = Carbon::now();

        $occurrences = $service->generateOccurrencesForRoutine($routine, $date, $date);
        $this->assertCount(0, $occurrences);
    }
}
