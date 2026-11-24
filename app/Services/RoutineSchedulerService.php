<?php

namespace App\Services;

use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class RoutineSchedulerService
{
    /**
     * Generate occurrences for a routine across a date range.
     * Idempotent: does not duplicate existing occurrences.
     */
    public function generateOccurrencesForRoutine(Routine $routine, Carbon $startDate, Carbon $endDate): Collection
    {
        $createdOccurrences = collect();

        // If routine is disabled or archived, don't generate new future occurrences
        if (! $routine->enabled || $routine->archived_at !== null) {
            return $createdOccurrences;
        }

        $schedules = $routine->schedules()->where('enabled', true)->get();

        foreach ($schedules as $schedule) {
            $occurrences = $this->generateForSchedule($schedule, $startDate, $endDate);
            $createdOccurrences = $createdOccurrences->merge($occurrences);
        }

        return $createdOccurrences;
    }

    /**
     * Generate occurrences for all active routines of a user.
     */
    public function generateForUser(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $timezone = $user->timezone ?: 'UTC';
        $start = $startDate ?: Carbon::now($timezone)->startOfDay();
        $end = $endDate ?: Carbon::now($timezone)->endOfDay();

        $allOccurrences = collect();
        $routines = $user->routines()->active()->with('schedules')->get();

        foreach ($routines as $routine) {
            $occurrences = $this->generateOccurrencesForRoutine($routine, $start, $end);
            $allOccurrences = $allOccurrences->merge($occurrences);
        }

        return $allOccurrences;
    }

    /**
     * Generate occurrences for a single schedule.
     */
    public function generateForSchedule(RoutineSchedule $schedule, Carbon $startDate, Carbon $endDate): Collection
    {
        $created = collect();
        $tz = $schedule->timezone ?: 'UTC';

        // Normalize start and end to schedule's timezone
        $periodStart = $startDate->copy()->timezone($tz)->startOfDay();
        $periodEnd = $endDate->copy()->timezone($tz)->endOfDay();

        // Check schedule boundaries
        if ($schedule->start_date && $periodStart->format('Y-m-d') < $schedule->start_date->format('Y-m-d')) {
            $periodStart = $schedule->start_date->copy()->timezone($tz)->startOfDay();
        }

        if ($schedule->end_date && $periodEnd->format('Y-m-d') > $schedule->end_date->format('Y-m-d')) {
            $periodEnd = $schedule->end_date->copy()->timezone($tz)->endOfDay();
        }

        if ($periodStart->gt($periodEnd)) {
            return $created;
        }

        // Parse local start time (e.g. "07:30" or "07:30:00")
        $timeParts = explode(':', $schedule->local_start_time);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);

        $period = CarbonPeriod::create($periodStart->format('Y-m-d'), '1 day', $periodEnd->format('Y-m-d'));

        foreach ($period as $date) {
            $dayOfWeek = (int) $date->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

            $shouldSchedule = false;

            switch ($schedule->recurrence_type) {
                case 'one_time':
                    $schedStart = $schedule->start_date ? $schedule->start_date->format('Y-m-d') : null;
                    if ($schedStart && $date->format('Y-m-d') === $schedStart) {
                        $shouldSchedule = true;
                    }
                    break;

                case 'daily':
                    $shouldSchedule = true;
                    break;

                case 'selected_weekdays':
                    $weekdays = $schedule->selected_weekdays ?? [];
                    // Ensure integer comparison
                    $intWeekdays = array_map('intval', (array) $weekdays);
                    if (in_array($dayOfWeek, $intWeekdays, true)) {
                        $shouldSchedule = true;
                    }
                    break;

                case 'weekly':
                    // Run on the day of week matching start_date or Sunday by default
                    $targetDay = $schedule->start_date ? (int) $schedule->start_date->dayOfWeek : 0;
                    if ($dayOfWeek === $targetDay) {
                        $shouldSchedule = true;
                    }
                    break;
            }

            if (! $shouldSchedule) {
                continue;
            }

            // Construct local datetime then convert to UTC for storage
            $localDue = Carbon::create(
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d'),
                $hour,
                $minute,
                0,
                $tz
            );

            $utcDue = $localDue->copy()->utc();

            // Idempotent insertion using firstOrCreate with unique keys
            $occurrence = TaskOccurrence::firstOrCreate(
                [
                    'routine_id' => $schedule->routine_id,
                    'schedule_id' => $schedule->id,
                    'due_at' => $utcDue,
                ],
                [
                    'status' => TaskOccurrence::STATUS_SCHEDULED,
                ]
            );

            $created->push($occurrence);
        }

        return $created;
    }
}
