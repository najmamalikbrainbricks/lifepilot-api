<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\AlarmEvent;
use App\Models\AlarmRule;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AlarmWorkflowService
{
    /**
     * Get due alarms for a user that should trigger an alert.
     * Evaluates quiet hours, snooze timestamps, attempt counts, and priorities.
     */
    public function getDueAlarms(User $user, ?Carbon $now = null): Collection
    {
        $tz = $user->timezone ?: 'UTC';
        $now = $now ? $now->copy()->timezone('UTC') : Carbon::now('UTC');
        $localNow = $now->copy()->timezone($tz);

        // Check if user is currently in quiet hours (bedtime to wake time)
        $isQuiet = $this->isInQuietHours($user, $localNow);

        // Fetch occurrences that are due now or overdue within the last 2 hours
        // and have not been completed, skipped, or cancelled.
        $lookback = $now->copy()->subHours(2);
        $lookahead = $now->copy()->addMinutes(1); // include anything due in next 60s

        $occurrences = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->where('enabled', true)
              ->whereNull('archived_at');
        })
        ->whereBetween('due_at', [$lookback, $lookahead])
        ->whereIn('status', [
            TaskOccurrence::STATUS_SCHEDULED,
            TaskOccurrence::STATUS_READY,
            TaskOccurrence::STATUS_DUE,
            TaskOccurrence::STATUS_RINGING,
            TaskOccurrence::STATUS_SNOOZED,
            TaskOccurrence::STATUS_ACKNOWLEDGED,
        ])
        ->with(['routine.steps', 'routine.alarmRule'])
        ->get();

        // Filter and prioritize
        $due = $occurrences->filter(function (TaskOccurrence $occ) use ($now, $isQuiet) {
            $alarmRule = $occ->routine->alarmRule;
            if ($alarmRule && ! $alarmRule->enabled) {
                return false;
            }

            // If snoozed, check if snooze interval has elapsed
            if ($occ->status === TaskOccurrence::STATUS_SNOOZED && $occ->snoozed_until) {
                if ($now->lt($occ->snoozed_until)) {
                    return false; // still in snooze period
                }
            }

            // Respect quiet hours unless alarm is urgent
            if ($isQuiet && $occ->routine->priority !== 'urgent') {
                return false;
            }

            // Check if maximum attempts reached
            $maxAttempts = $alarmRule ? $alarmRule->max_attempts : 3;
            if ($occ->alarm_attempts >= $maxAttempts && $occ->status === TaskOccurrence::STATUS_SNOOZED) {
                // Auto mark as missed if exceeded
                $this->autoMarkMissed($occ);
                return false;
            }

            return true;
        });

        // Priority queue sort: priority (urgent=1, high=2, medium=3, low=4), then earliest due_at
        return $due->sort(function (TaskOccurrence $a, TaskOccurrence $b) {
            $pOrder = ['urgent' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
            $pA = $pOrder[$a->routine->priority ?? 'medium'] ?? 3;
            $pB = $pOrder[$b->routine->priority ?? 'medium'] ?? 3;

            if ($pA !== $pB) {
                return $pA <=> $pB;
            }

            return $a->due_at <=> $b->due_at;
        })->values();
    }

    /**
     * Check if local time falls into user's configured quiet hours.
     */
    public function isInQuietHours(User $user, Carbon $localNow): bool
    {
        $pref = $user->preference;
        if (! $pref || ! $pref->bedtime || ! $pref->wake_time) {
            return false;
        }

        $currentTime = $localNow->format('H:i');
        $bedtime = $pref->bedtime;
        $wakeTime = $pref->wake_time;

        // If bedtime spans midnight (e.g. 23:00 to 07:00)
        if ($bedtime > $wakeTime) {
            return $currentTime >= $bedtime || $currentTime < $wakeTime;
        }

        return $currentTime >= $bedtime && $currentTime < $wakeTime;
    }

    /**
     * Mark an occurrence as actively ringing.
     */
    public function triggerRinging(TaskOccurrence $occurrence, User $user): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $user) {
            $now = Carbon::now('UTC');

            $occurrence->status = TaskOccurrence::STATUS_RINGING;
            $occurrence->ringing_started_at = $now;
            $occurrence->alarm_attempts = ($occurrence->alarm_attempts ?? 0) + 1;
            $occurrence->save();

            // Log alarm event
            AlarmEvent::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'alarm_rule_id' => $occurrence->routine->alarmRule?->id,
                'event_type' => AlarmEvent::EVENT_RINGING,
                'event_time' => $now,
                'metadata' => [
                    'attempt' => $occurrence->alarm_attempts,
                    'routine_title' => $occurrence->routine->title,
                    'sound' => $occurrence->routine->alarmRule?->sound ?? 'flowing_water',
                ],
            ]);

            return $occurrence->fresh(['routine.steps', 'routine.alarmRule']);
        });
    }

    /**
     * Acknowledge alarm.
     * Transitions state from ringing to acknowledged or preparation / in_progress.
     */
    public function acknowledgeAlarm(TaskOccurrence $occurrence, User $user): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $user) {
            $now = Carbon::now('UTC');

            $ringDurationSeconds = null;
            if ($occurrence->ringing_started_at) {
                $ringDurationSeconds = (int) $occurrence->ringing_started_at->diffInSeconds($now);
            }

            $occurrence->status = TaskOccurrence::STATUS_ACKNOWLEDGED;
            $occurrence->acknowledged_at = $now;
            $occurrence->save();

            AlarmEvent::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'alarm_rule_id' => $occurrence->routine->alarmRule?->id,
                'event_type' => AlarmEvent::EVENT_ACKNOWLEDGED,
                'event_time' => $now,
                'metadata' => [
                    'ring_duration_seconds' => $ringDurationSeconds,
                    'attempt' => $occurrence->alarm_attempts,
                ],
            ]);

            return $occurrence->fresh(['routine.steps', 'routine.alarmRule']);
        });
    }

    /**
     * Snooze alarm for N minutes.
     */
    public function snoozeAlarm(TaskOccurrence $occurrence, ?int $minutes, User $user): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $minutes, $user) {
            $alarmRule = $occurrence->routine->alarmRule;
            $snoozeMinutes = $minutes ?: ($alarmRule ? $alarmRule->snooze_duration : 5);
            $maxAttempts = $alarmRule ? $alarmRule->max_attempts : 3;

            $now = Carbon::now('UTC');

            // If snooze attempts exceed maximum, escalate or mark missed
            if (($occurrence->alarm_attempts ?? 0) >= $maxAttempts) {
                return $this->autoMarkMissed($occurrence);
            }

            $occurrence->status = TaskOccurrence::STATUS_SNOOZED;
            $occurrence->snoozed_until = $now->copy()->addMinutes($snoozeMinutes);
            $occurrence->save();

            AlarmEvent::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'alarm_rule_id' => $alarmRule?->id,
                'event_type' => AlarmEvent::EVENT_SNOOZED,
                'event_time' => $now,
                'metadata' => [
                    'snooze_minutes' => $snoozeMinutes,
                    'snoozed_until' => $occurrence->snoozed_until->toIso8601String(),
                    'attempt' => $occurrence->alarm_attempts,
                    'max_attempts' => $maxAttempts,
                ],
            ]);

            return $occurrence->fresh(['routine.steps', 'routine.alarmRule']);
        });
    }

    /**
     * Dismiss alarm (e.g. user manually stops alarm without immediately starting).
     */
    public function dismissAlarm(TaskOccurrence $occurrence, User $user, ?string $reason = null): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $user, $reason) {
            $now = Carbon::now('UTC');

            $occurrence->status = TaskOccurrence::STATUS_READY;
            $occurrence->save();

            AlarmEvent::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'alarm_rule_id' => $occurrence->routine->alarmRule?->id,
                'event_type' => AlarmEvent::EVENT_DISMISSED,
                'event_time' => $now,
                'metadata' => [
                    'reason' => $reason,
                ],
            ]);

            return $occurrence->fresh(['routine.steps', 'routine.alarmRule']);
        });
    }

    /**
     * Emergency Stop: immediately silence and dismiss all ringing or due alarms for user.
     */
    public function emergencyStop(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $now = Carbon::now('UTC');

            $activeOccurrences = TaskOccurrence::whereHas('routine', fn ($q) => $q->where('user_id', $user->id))
                ->whereIn('status', [TaskOccurrence::STATUS_RINGING, TaskOccurrence::STATUS_DUE])
                ->get();

            $count = 0;
            foreach ($activeOccurrences as $occ) {
                $occ->status = TaskOccurrence::STATUS_READY;
                $occ->save();

                AlarmEvent::create([
                    'user_id' => $user->id,
                    'occurrence_id' => $occ->id,
                    'alarm_rule_id' => $occ->routine->alarmRule?->id,
                    'event_type' => AlarmEvent::EVENT_EMERGENCY_STOP,
                    'event_time' => $now,
                    'metadata' => [
                        'action' => 'emergency_stop_triggered',
                    ],
                ]);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Automatically mark occurrence as missed when max attempts exceeded.
     */
    protected function autoMarkMissed(TaskOccurrence $occurrence): TaskOccurrence
    {
        $now = Carbon::now('UTC');
        $occurrence->status = TaskOccurrence::STATUS_MISSED;
        $occurrence->save();

        AlarmEvent::create([
            'user_id' => $occurrence->routine->user_id,
            'occurrence_id' => $occurrence->id,
            'alarm_rule_id' => $occurrence->routine->alarmRule?->id,
            'event_type' => AlarmEvent::EVENT_AUTO_MISSED,
            'event_time' => $now,
            'metadata' => [
                'reason' => 'max_attempts_exceeded',
                'attempts' => $occurrence->alarm_attempts,
            ],
        ]);

        return $occurrence;
    }
}
