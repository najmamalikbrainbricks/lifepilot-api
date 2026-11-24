<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\Routine;
use App\Models\UserPreference;
use App\Models\WorkShift;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataMigrationController extends Controller
{
    public function __construct(
        protected RoutineSchedulerService $schedulerService
    ) {}

    public function import(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->all();

        $summary = [
            'imported_routines' => 0,
            'skipped_routines' => 0,
            'imported_shifts' => 0,
            'skipped_shifts' => 0,
            'imported_goals' => 0,
            'skipped_goals' => 0,
            'settings_updated' => false,
            'errors' => [],
        ];

        DB::transaction(function () use ($user, $payload, &$summary) {
            // 1. Migrate settings if provided
            if (isset($payload['settings']) && is_array($payload['settings'])) {
                $s = $payload['settings'];
                $pref = UserPreference::firstOrNew(['user_id' => $user->id]);

                if (! empty($s['timezone'])) {
                    $user->timezone = $s['timezone'];
                    $user->save();
                }

                $pref->fill([
                    'theme' => $s['theme'] ?? $pref->theme ?? 'dark',
                    'time_format' => $s['timeFormat'] ?? $pref->time_format ?? '12h',
                    'sleep_goal' => isset($s['sleepGoalHours']) ? (float) $s['sleepGoalHours'] : 8.0,
                    'bedtime' => $s['bedtime'] ?? '01:30',
                    'wake_time' => $s['wakeTime'] ?? '09:30',
                    'focus_goal' => isset($s['focusGoalMinutes']) ? (int) $s['focusGoalMinutes'] : 60,
                    'focus_category' => $s['focusCategory'] ?? 'Agency',
                    'reminder_preferences' => [
                        'reminder_interval_minutes' => $s['reminderIntervalMinutes'] ?? 5,
                        'strict_mode' => (bool) ($s['strictMode'] ?? false),
                        'prayers_enabled' => (bool) ($s['prayersEnabled'] ?? true),
                        'wudu_reminders' => (bool) ($s['wuduReminders'] ?? true),
                        'tahajjud_enabled' => (bool) ($s['tahajjudEnabled'] ?? true),
                        'prayer_method' => $s['prayerMethod'] ?? 'Manual',
                        'prayer_proof' => $s['prayerProof'] ?? 'self',
                    ],
                    'activity_preferences' => [
                        'step_goal' => $s['stepGoal'] ?? 6000,
                        'workout_days' => $s['workoutDays'] ?? 3,
                    ],
                ]);
                $pref->user_id = $user->id;
                $pref->save();
                $summary['settings_updated'] = true;

                // 2. Migrate goals from settings.goals
                if (isset($s['goals']) && is_array($s['goals'])) {
                    foreach ($s['goals'] as $index => $goalTitle) {
                        $title = trim((string) $goalTitle);
                        if (! $title) continue;

                        $exists = Goal::where('user_id', $user->id)->where('title', $title)->exists();
                        if ($exists) {
                            $summary['skipped_goals']++;
                        } else {
                            Goal::create([
                                'user_id' => $user->id,
                                'title' => $title,
                                'priority' => $index + 1,
                                'active' => true,
                            ]);
                            $summary['imported_goals']++;
                        }
                    }
                }

                // 3. Migrate shifts from settings.shifts
                if (isset($s['shifts']) && is_array($s['shifts'])) {
                    foreach ($s['shifts'] as $shiftData) {
                        $jobName = trim($shiftData['name'] ?? '');
                        if (! $jobName) continue;

                        $startTime = $shiftData['start'] ?? '19:00';
                        $endTime = $shiftData['end'] ?? '01:00';
                        $days = $shiftData['days'] ?? [1,2,3,4,5];
                        $fixed = isset($shiftData['fixed']) ? ($shiftData['fixed'] ? 'fixed' : 'flexible') : 'fixed';
                        $overnight = ($endTime < $startTime);

                        $exists = WorkShift::where('user_id', $user->id)
                            ->where('job_name', $jobName)
                            ->where('start_time', $startTime)
                            ->exists();

                        if ($exists) {
                            $summary['skipped_shifts']++;
                        } else {
                            WorkShift::create([
                                'user_id' => $user->id,
                                'job_name' => $jobName,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'days_of_week' => $days,
                                'fixed_or_flexible' => $fixed,
                                'overnight' => $overnight,
                                'enabled' => true,
                            ]);
                            $summary['imported_shifts']++;
                        }
                    }
                }
            }

            // 4. Migrate tasks array to Routines and Schedules
            if (isset($payload['tasks']) && is_array($payload['tasks'])) {
                foreach ($payload['tasks'] as $t) {
                    try {
                        $title = trim($t['title'] ?? '');
                        if (! $title) continue;

                        // Check if routine already exists with this title
                        $exists = Routine::where('user_id', $user->id)->where('title', $title)->first();
                        if ($exists) {
                            $summary['skipped_routines']++;
                            continue;
                        }

                        $category = $t['category'] ?? 'Personal';
                        $duration = isset($t['durationMinutes']) ? (int) $t['durationMinutes'] : 15;

                        // Parse time string e.g. "7:00 AM" to 24h "07:00"
                        $timeStr = $t['time'] ?? '09:00 AM';
                        $localTime = '09:00';
                        if (preg_match('/(\d+):(\d+)\s*(AM|PM)/i', $timeStr, $matches)) {
                            $hour = (int) $matches[1] % 12;
                            if (strtoupper($matches[3]) === 'PM') $hour += 12;
                            $localTime = sprintf('%02d:%02d', $hour, (int) $matches[2]);
                        } elseif (preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                            $localTime = $timeStr;
                        }

                        $routine = Routine::create([
                            'user_id' => $user->id,
                            'title' => $title,
                            'description' => $t['note'] ?? null,
                            'category' => $category,
                            'priority' => 'medium',
                            'estimated_duration' => $duration,
                            'enabled' => true,
                        ]);

                        // Add default main step
                        $routine->steps()->create([
                            'title' => $title,
                            'description' => 'Complete ' . $title,
                            'sort_order' => 0,
                            'estimated_duration' => $duration,
                            'required' => true,
                            'verification_type' => 'self',
                        ]);

                        // Add daily schedule
                        $schedule = $routine->schedules()->create([
                            'recurrence_type' => 'daily',
                            'local_start_time' => $localTime,
                            'timezone' => $user->timezone ?: 'UTC',
                            'enabled' => true,
                        ]);

                        // Add default alarm rule
                        $routine->alarmRule()->create([
                            'enabled' => true,
                            'sound' => 'flowing_water',
                            'repeat_interval' => 5,
                            'max_attempts' => 3,
                            'snooze_duration' => 5,
                            'verification_required' => false,
                        ]);

                        // Generate today's occurrence
                        $userTz = $user->timezone ?: 'UTC';
                        $today = Carbon::now($userTz)->startOfDay();
                        $this->schedulerService->generateForSchedule($schedule, $today, $today);

                        $summary['imported_routines']++;
                    } catch (Exception $e) {
                        $summary['errors'][] = "Failed to import '{$t['title']}': " . $e->getMessage();
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Migration processed successfully',
            'summary' => $summary,
        ]);
    }
}
