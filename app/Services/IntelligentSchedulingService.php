<?php

namespace App\Services;

use App\Models\ProtectedTimeBlock;
use App\Models\Routine;
use App\Models\TaskOccurrence;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IntelligentSchedulingService
{
    public function __construct(
        protected PrayerTimeService $prayerService,
        protected RoutineSchedulerService $routineScheduler
    ) {}

    /**
     * Get a comprehensive, consolidated daily timeline for a user on a given date.
     * Integrates:
     * 1. Work shifts (including overnight shifts)
     * 2. Prayer times & Tahajjud
     * 3. Task occurrences
     * 4. Protected focus blocks
     * 5. Sleep & Wind-down
     * 6. Free time slots
     * 7. Detected schedule conflicts
     */
    public function getDailyTimeline(User $user, Carbon $date): array
    {
        $tz = $user->timezone ?: 'Asia/Karachi';
        $localDate = $date->copy()->timezone($tz)->startOfDay();
        $dateStr = $localDate->format('Y-m-d');
        $dayOfWeek = (int) $localDate->dayOfWeek; // 0 (Sun) to 6 (Sat)

        // Ensure occurrences are generated for this date
        $this->routineScheduler->generateForUser($user, $localDate, $localDate->copy()->endOfDay());

        $blocks = collect();

        // 1. Sleep & Wind-down Block (from user preferences)
        $pref = $user->preference;
        $bedtimeStr = $pref?->bedtime ?: '01:30';
        $wakeTimeStr = $pref?->wake_time ?: '09:30';

        // Bedtime often crosses midnight or morning
        $wakeTime = Carbon::parse("{$dateStr} {$wakeTimeStr}", $tz);
        $blocks->push([
            'id' => 'sleep-morning',
            'type' => 'sleep',
            'title' => 'Sleep & Rest',
            'category' => 'Sleep',
            'start_time' => '00:00',
            'end_time' => $wakeTimeStr,
            'start_datetime' => Carbon::parse("{$dateStr} 00:00", $tz)->toIso8601String(),
            'end_datetime' => $wakeTime->toIso8601String(),
            'duration_minutes' => (int) Carbon::parse("{$dateStr} 00:00", $tz)->diffInMinutes($wakeTime),
            'priority' => 1,
            'color' => '#8b5cf6',
            'is_immovable' => true,
        ]);

        // 2. Prayer Times
        $prayerData = $this->prayerService->getTimesForDate($user, $localDate);
        $effectivePrayers = $prayerData['effective_times'];
        $prayerDurations = [
            'fajr' => 20,
            'dhuhr' => 25,
            'asr' => 20,
            'maghrib' => 20,
            'isha' => 25,
        ];

        foreach ($effectivePrayers as $prayer => $timeStr) {
            if ($prayer === 'sunrise') continue;

            $prayerStart = Carbon::parse("{$dateStr} {$timeStr}", $tz);
            $duration = $prayerDurations[$prayer] ?? 20;
            $prayerEnd = $prayerStart->copy()->addMinutes($duration);

            $completion = $prayerData['completions'][$prayer] ?? null;

            $blocks->push([
                'id' => "prayer-{$prayer}",
                'type' => 'prayer',
                'prayer_name' => $prayer,
                'title' => ucfirst($prayer) . ' Prayer',
                'category' => 'Spiritual',
                'start_time' => $timeStr,
                'end_time' => $prayerEnd->format('H:i'),
                'start_datetime' => $prayerStart->toIso8601String(),
                'end_datetime' => $prayerEnd->toIso8601String(),
                'duration_minutes' => $duration,
                'priority' => 2,
                'color' => '#0ea5e9',
                'is_immovable' => true,
                'status' => $completion ? $completion['status'] : 'scheduled',
                'has_override' => $prayerData['overrides']['by_prayer'][$prayer] ?? false,
            ]);
        }

        // Tahajjud window
        if (! empty($prayerData['tahajjud']['enabled'])) {
            $tahajjudStartStr = $prayerData['tahajjud']['suggested_start'];
            $tahajjudDur = (int) $prayerData['tahajjud']['duration_minutes'];
            $tStart = Carbon::parse("{$dateStr} {$tahajjudStartStr}", $tz);
            $tEnd = $tStart->copy()->addMinutes($tahajjudDur);

            $blocks->push([
                'id' => 'prayer-tahajjud',
                'type' => 'prayer',
                'prayer_name' => 'tahajjud',
                'title' => 'Tahajjud (Night Prayer)',
                'category' => 'Spiritual',
                'start_time' => $tahajjudStartStr,
                'end_time' => $tEnd->format('H:i'),
                'start_datetime' => $tStart->toIso8601String(),
                'end_datetime' => $tEnd->toIso8601String(),
                'duration_minutes' => $tahajjudDur,
                'priority' => 3,
                'color' => '#38bdf8',
                'is_immovable' => false,
                'note' => 'Last third of the night',
            ]);
        }

        // 3. Work Shifts (Handle regular and overnight shifts)
        $shifts = $user->workShifts()->where('enabled', true)->get();

        foreach ($shifts as $shift) {
            $shiftDays = (array) ($shift->days_of_week ?? []);

            // Check if shift starts today
            if (in_array($dayOfWeek, $shiftDays, true)) {
                $shiftStart = Carbon::parse("{$dateStr} {$shift->start_time}", $tz);

                if ($shift->overnight || $shift->end_time < $shift->start_time) {
                    // Finishes tomorrow
                    $shiftEnd = Carbon::parse("{$dateStr} {$shift->end_time}", $tz)->addDay();
                } else {
                    $shiftEnd = Carbon::parse("{$dateStr} {$shift->end_time}", $tz);
                }

                $durationMin = (int) $shiftStart->diffInMinutes($shiftEnd);

                $blocks->push([
                    'id' => "work-shift-{$shift->id}",
                    'type' => 'work_shift',
                    'title' => $shift->job_name,
                    'category' => 'Work',
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'start_datetime' => $shiftStart->toIso8601String(),
                    'end_datetime' => $shiftEnd->toIso8601String(),
                    'duration_minutes' => $durationMin,
                    'priority' => 1,
                    'color' => '#3b82f6',
                    'is_immovable' => ($shift->fixed_or_flexible === 'fixed'),
                    'overnight' => (bool) $shift->overnight,
                ]);
            }

            // Check if an overnight shift started yesterday and continues into today
            $prevDayOfWeek = ($dayOfWeek + 6) % 7;
            if ($shift->overnight && in_array($prevDayOfWeek, $shiftDays, true)) {
                $prevEnd = Carbon::parse("{$dateStr} {$shift->end_time}", $tz);
                $blocks->push([
                    'id' => "work-shift-{$shift->id}-carryover",
                    'type' => 'work_shift',
                    'title' => "{$shift->job_name} (Overnight finish)",
                    'category' => 'Work',
                    'start_time' => '00:00',
                    'end_time' => $shift->end_time,
                    'start_datetime' => Carbon::parse("{$dateStr} 00:00", $tz)->toIso8601String(),
                    'end_datetime' => $prevEnd->toIso8601String(),
                    'duration_minutes' => (int) Carbon::parse("{$dateStr} 00:00", $tz)->diffInMinutes($prevEnd),
                    'priority' => 1,
                    'color' => '#3b82f6',
                    'is_immovable' => true,
                    'carryover' => true,
                ]);
            }
        }

        // 4. Protected Focus Time Blocks
        $focusBlocks = $user->protectedTimeBlocks()->where('enabled', true)->get();
        foreach ($focusBlocks as $fBlock) {
            $fDays = (array) ($fBlock->days_of_week ?? []);
            if (empty($fDays) || in_array($dayOfWeek, $fDays, true)) {
                $fStart = Carbon::parse("{$dateStr} {$fBlock->start_time}", $tz);
                $fEnd = Carbon::parse("{$dateStr} {$fBlock->end_time}", $tz);

                $blocks->push([
                    'id' => "focus-block-{$fBlock->id}",
                    'type' => 'focus_block',
                    'title' => $fBlock->title,
                    'category' => $fBlock->category ?: 'Focus',
                    'start_time' => $fBlock->start_time,
                    'end_time' => $fBlock->end_time,
                    'start_datetime' => $fStart->toIso8601String(),
                    'end_datetime' => $fEnd->toIso8601String(),
                    'duration_minutes' => (int) $fStart->diffInMinutes($fEnd),
                    'priority' => 2,
                    'color' => '#a855f7',
                    'is_immovable' => false,
                ]);
            }
        }

        // 5. Task Occurrences for Today
        $startUtc = $localDate->copy()->utc();
        $endUtc = $localDate->copy()->endOfDay()->utc();

        $occurrences = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereBetween('due_at', [$startUtc, $endUtc])
        ->with(['routine.steps', 'routine.alarmRule', 'stepCompletions.step'])
        ->get();

        foreach ($occurrences as $occ) {
            $occStart = $occ->due_at->copy()->timezone($tz);
            $duration = (int) ($occ->routine->estimated_duration ?: 15);
            $occEnd = $occStart->copy()->addMinutes($duration);

            $blocks->push([
                'id' => "occurrence-{$occ->id}",
                'type' => 'routine_occurrence',
                'occurrence_id' => $occ->id,
                'routine_id' => $occ->routine_id,
                'title' => $occ->routine->title,
                'category' => $occ->routine->category,
                'start_time' => $occStart->format('H:i'),
                'end_time' => $occEnd->format('H:i'),
                'start_datetime' => $occStart->toIso8601String(),
                'end_datetime' => $occEnd->toIso8601String(),
                'duration_minutes' => $duration,
                'priority' => match ($occ->routine->priority) {
                    'urgent' => 1,
                    'high' => 2,
                    'medium' => 3,
                    default => 4,
                },
                'color' => $occ->routine->color ?: '#74c7c3',
                'status' => $occ->status,
                'is_rescheduled' => (bool) $occ->is_rescheduled,
                'rescheduled_at' => $occ->rescheduled_at?->toIso8601String(),
                'original_due_at' => $occ->original_due_at?->timezone($tz)->format('H:i'),
                'reschedule_reason' => $occ->reschedule_reason,
                'is_immovable' => false,
                'steps_count' => $occ->routine->steps->count(),
                'completed_steps' => $occ->stepCompletions->whereIn('status', ['completed', 'overridden'])->count(),
            ]);
        }

        // 6. Detect Schedule Conflicts
        $conflicts = $this->detectConflicts($blocks, $tz);

        // 7. Calculate Free Slots
        $freeSlots = $this->calculateFreeSlots($blocks, $localDate, $tz);

        // Sort chronological
        $sortedBlocks = $blocks->sortBy('start_time')->values();

        return [
            'date' => $dateStr,
            'date_formatted' => $localDate->format('l, F j, Y'),
            'timezone' => $tz,
            'timeline_blocks' => $sortedBlocks,
            'conflicts' => $conflicts,
            'free_slots' => $freeSlots,
            'prayer_summary' => [
                'fajr' => $effectivePrayers['fajr'],
                'dhuhr' => $effectivePrayers['dhuhr'],
                'asr' => $effectivePrayers['asr'],
                'maghrib' => $effectivePrayers['maghrib'],
                'isha' => $effectivePrayers['isha'],
                'tahajjud' => $prayerData['tahajjud']['suggested_start'] ?? null,
            ],
            'stats' => [
                'total_routines' => $occurrences->count(),
                'completed_routines' => $occurrences->whereIn('status', ['completed', 'partially_completed'])->count(),
                'conflicts_count' => count($conflicts),
                'free_minutes' => $freeSlots->sum('duration_minutes'),
            ],
        ];
    }

    /**
     * Detect overlaps and conflicts between active schedule blocks.
     */
    public function detectConflicts(Collection $blocks, string $tz): array
    {
        $conflicts = [];
        $activeBlocks = $blocks->filter(function ($b) {
            // Ignore sleep morning, completed tasks, or cancelled items
            return ! in_array($b['status'] ?? '', ['completed', 'cancelled', 'skipped'], true);
        })->values();

        $count = $activeBlocks->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $activeBlocks[$i];
                $b = $activeBlocks[$j];

                $aStart = Carbon::parse($a['start_datetime'], $tz);
                $aEnd = Carbon::parse($a['end_datetime'], $tz);

                $bStart = Carbon::parse($b['start_datetime'], $tz);
                $bEnd = Carbon::parse($b['end_datetime'], $tz);

                // Check overlap: startA < endB && endA > startB
                if ($aStart->lt($bEnd) && $aEnd->gt($bStart)) {
                    $overlapMinutes = min($aEnd, $bEnd)->diffInMinutes(max($aStart, $bStart));

                    // Categorize conflict
                    $conflictType = 'overlap';
                    $severity = 'medium';
                    $description = "{$a['title']} overlaps with {$b['title']} by {$overlapMinutes} minutes.";

                    if ($a['type'] === 'work_shift' || $b['type'] === 'work_shift') {
                        $conflictType = 'work_conflict';
                        $severity = 'high';
                        $description = "Conflict with fixed work shift: {$a['title']} and {$b['title']}.";
                    } elseif ($a['type'] === 'prayer' || $b['type'] === 'prayer') {
                        $conflictType = 'prayer_conflict';
                        $severity = 'high';
                        $description = "Conflict with prayer time: {$a['title']} overlaps {$b['title']}.";
                    } elseif ($a['type'] === 'routine_occurrence' && $b['type'] === 'routine_occurrence') {
                        $conflictType = 'routine_overlap';
                        $severity = 'medium';
                    }

                    $conflicts[] = [
                        'type' => $conflictType,
                        'severity' => $severity,
                        'description' => $description,
                        'overlap_minutes' => (int) $overlapMinutes,
                        'item_a' => [
                            'id' => $a['id'],
                            'title' => $a['title'],
                            'type' => $a['type'],
                            'time' => "{$a['start_time']} - {$a['end_time']}",
                        ],
                        'item_b' => [
                            'id' => $b['id'],
                            'title' => $b['title'],
                            'type' => $b['type'],
                            'time' => "{$b['start_time']} - {$b['end_time']}",
                        ],
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Calculate free uninterrupted time windows during waking hours (e.g. 06:00 to 23:59).
     */
    public function calculateFreeSlots(Collection $blocks, Carbon $date, string $tz): Collection
    {
        $freeSlots = collect();
        $dateStr = $date->format('Y-m-d');

        // Working day window: 06:00 to 23:59
        $dayStart = Carbon::parse("{$dateStr} 06:00", $tz);
        $dayEnd = Carbon::parse("{$dateStr} 23:59", $tz);

        // Filter busy blocks that fall within the waking window
        $busyIntervals = [];

        foreach ($blocks as $b) {
            $start = Carbon::parse($b['start_datetime'], $tz);
            $end = Carbon::parse($b['end_datetime'], $tz);

            if ($end->lte($dayStart) || $start->gte($dayEnd)) {
                continue;
            }

            $effectiveStart = $start->lt($dayStart) ? $dayStart->copy() : $start;
            $effectiveEnd = $end->gt($dayEnd) ? $dayEnd->copy() : $end;

            $busyIntervals[] = [$effectiveStart, $effectiveEnd];
        }

        // Merge overlapping busy intervals
        usort($busyIntervals, fn ($x, $y) => $x[0]->timestamp <=> $y[0]->timestamp);

        $mergedBusy = [];
        foreach ($busyIntervals as $interval) {
            if (empty($mergedBusy)) {
                $mergedBusy[] = $interval;
                continue;
            }

            $lastIdx = count($mergedBusy) - 1;
            if ($interval[0]->lte($mergedBusy[$lastIdx][1])) {
                // Overlap: extend end if needed
                if ($interval[1]->gt($mergedBusy[$lastIdx][1])) {
                    $mergedBusy[$lastIdx][1] = $interval[1];
                }
            } else {
                $mergedBusy[] = $interval;
            }
        }

        // Invert busy intervals to find free intervals
        $current = $dayStart->copy();
        foreach ($mergedBusy as $busy) {
            if ($busy[0]->gt($current)) {
                $duration = (int) $current->diffInMinutes($busy[0]);
                if ($duration >= 15) { // Minimum 15-minute slot
                    $freeSlots->push([
                        'start_time' => $current->format('H:i'),
                        'end_time' => $busy[0]->format('H:i'),
                        'duration_minutes' => $duration,
                    ]);
                }
            }
            $current = $busy[1]->gt($current) ? $busy[1]->copy() : $current;
        }

        if ($current->lt($dayEnd)) {
            $duration = (int) $current->diffInMinutes($dayEnd);
            if ($duration >= 15) {
                $freeSlots->push([
                    'start_time' => $current->format('H:i'),
                    'end_time' => $dayEnd->format('H:i'),
                    'duration_minutes' => $duration,
                ]);
            }
        }

        return $freeSlots;
    }

    /**
     * Propose intelligent available slots for a routine occurrence.
     */
    public function suggestAvailableSlots(User $user, int $durationMinutes, Carbon $date): array
    {
        $timeline = $this->getDailyTimeline($user, $date);
        $freeSlots = $timeline['free_slots'];

        $suggestions = [];

        foreach ($freeSlots as $slot) {
            if ($slot['duration_minutes'] >= $durationMinutes) {
                $slotStart = Carbon::parse("{$timeline['date']} {$slot['start_time']}", $timeline['timezone']);
                $proposedEnd = $slotStart->copy()->addMinutes($durationMinutes);

                $suggestions[] = [
                    'suggested_start_time' => $slot['start_time'],
                    'suggested_end_time' => $proposedEnd->format('H:i'),
                    'slot_duration_minutes' => $slot['duration_minutes'],
                    'required_duration_minutes' => $durationMinutes,
                    'rationale' => "Uninterrupted {$slot['duration_minutes']}-minute free window between commitments.",
                    'fits_comfortably' => ($slot['duration_minutes'] >= $durationMinutes + 15),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Reschedule an individual occurrence without affecting the recurring schedule.
     */
    public function rescheduleOccurrence(TaskOccurrence $occurrence, Carbon $newDueAt, ?string $reason = null): TaskOccurrence
    {
        // Preserve original due at
        if (! $occurrence->original_due_at) {
            $occurrence->original_due_at = $occurrence->due_at;
        }

        $occurrence->due_at = $newDueAt->copy()->utc();
        $occurrence->rescheduled_at = Carbon::now();
        $occurrence->reschedule_reason = $reason;
        $occurrence->is_rescheduled = true;

        if ($occurrence->status === TaskOccurrence::STATUS_MISSED) {
            $occurrence->status = TaskOccurrence::STATUS_SCHEDULED;
        }

        $occurrence->save();

        return $occurrence->fresh(['routine.steps', 'schedule', 'stepCompletions.step']);
    }

    /**
     * Reset a rescheduled occurrence back to its original time.
     */
    public function resetReschedule(TaskOccurrence $occurrence): TaskOccurrence
    {
        if ($occurrence->original_due_at) {
            $occurrence->due_at = $occurrence->original_due_at;
            $occurrence->original_due_at = null;
            $occurrence->rescheduled_at = null;
            $occurrence->reschedule_reason = null;
            $occurrence->is_rescheduled = false;
            $occurrence->save();
        }

        return $occurrence->fresh(['routine.steps', 'schedule', 'stepCompletions.step']);
    }

    /**
     * 7-day Weekly Calendar Aggregation.
     */
    public function getWeeklyCalendar(User $user, Carbon $startDate): array
    {
        $tz = $user->timezone ?: 'Asia/Karachi';
        $start = $startDate->copy()->timezone($tz)->startOfWeek(); // Monday
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $daily = $this->getDailyTimeline($user, $day);

            $days[] = [
                'date' => $day->format('Y-m-d'),
                'day_name' => $day->format('D'),
                'day_number' => (int) $day->format('j'),
                'is_today' => $day->isToday(),
                'routines_count' => $daily['stats']['total_routines'],
                'completed_count' => $daily['stats']['completed_routines'],
                'conflicts_count' => $daily['stats']['conflicts_count'],
                'free_minutes' => $daily['stats']['free_minutes'],
                'work_shifts' => $daily['timeline_blocks']->where('type', 'work_shift')->values(),
                'prayers' => $daily['prayer_summary'],
                'occurrences' => $daily['timeline_blocks']->where('type', 'routine_occurrence')->values(),
            ];
        }

        return [
            'week_start' => $start->format('Y-m-d'),
            'week_end' => $start->copy()->addDays(6)->format('Y-m-d'),
            'timezone' => $tz,
            'days' => $days,
        ];
    }

    /**
     * Monthly Calendar Grid Aggregation.
     */
    public function getMonthlyCalendar(User $user, int $year, int $month): array
    {
        $tz = $user->timezone ?: 'Asia/Karachi';
        $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
        $daysInMonth = $startOfMonth->daysInMonth;

        $days = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = Carbon::create($year, $month, $d, 0, 0, 0, $tz);
            $dateStr = $date->format('Y-m-d');

            // Find occurrences for this day
            $startUtc = $date->copy()->startOfDay()->utc();
            $endUtc = $date->copy()->endOfDay()->utc();

            $occurrences = TaskOccurrence::whereHas('routine', fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('due_at', [$startUtc, $endUtc])
                ->get();

            $completed = $occurrences->whereIn('status', ['completed', 'partially_completed'])->count();
            $missed = $occurrences->where('status', 'missed')->count();

            // Prayer completion counts
            $prayerCompletions = $user->prayerCompletions()->where('date', $dateStr)->count();

            $days[] = [
                'date' => $dateStr,
                'day' => $d,
                'day_name' => $date->format('D'),
                'is_today' => $date->isToday(),
                'total_tasks' => $occurrences->count(),
                'completed_tasks' => $completed,
                'missed_tasks' => $missed,
                'prayers_completed' => $prayerCompletions,
                'completion_percent' => $occurrences->count() > 0 ? (int) round(($completed / $occurrences->count()) * 100) : 0,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => $startOfMonth->format('F Y'),
            'timezone' => $tz,
            'days' => $days,
        ];
    }
}
