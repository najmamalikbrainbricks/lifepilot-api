<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Models\TimerSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TimerSessionService
{
    /**
     * Start a new timer session.
     */
    public function startSession(
        User $user,
        TaskOccurrence $occurrence,
        ?RoutineStep $step = null,
        ?int $targetDurationSeconds = null,
        ?array $metadata = []
    ): TimerSession {
        if ($occurrence->routine->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized occurrence');
        }

        if ($step && $step->routine_id !== $occurrence->routine_id) {
            throw new InvalidArgumentException('Step does not belong to occurrence routine');
        }

        return DB::transaction(function () use ($user, $occurrence, $step, $targetDurationSeconds, $metadata) {
            $now = Carbon::now('UTC');

            // Create new timer session
            $session = TimerSession::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'routine_step_id' => $step?->id,
                'started_at' => $now,
                'elapsed_seconds' => 0,
                'status' => TimerSession::STATUS_RUNNING,
                'target_duration_seconds' => $targetDurationSeconds,
                'metadata' => $metadata,
            ]);

            ActivityEvent::create([
                'user_id' => $user->id,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'timer_started',
                'event_time' => $now,
                'metadata' => [
                    'session_id' => $session->id,
                    'step_id' => $step?->id,
                    'target_seconds' => $targetDurationSeconds,
                ],
            ]);

            return $session;
        });
    }

    /**
     * Pause an active timer session.
     */
    public function pauseSession(TimerSession $session, User $user): TimerSession
    {
        if ($session->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized timer session');
        }

        if ($session->status !== TimerSession::STATUS_RUNNING) {
            return $session;
        }

        $now = Carbon::now('UTC');
        $session->paused_at = $now;

        // Calculate increment since started or last resumed
        if ($session->started_at) {
            $additionalSeconds = (int) $session->started_at->diffInSeconds($now);
            $session->elapsed_seconds += $additionalSeconds;
        }

        $session->status = TimerSession::STATUS_PAUSED;
        $session->save();

        ActivityEvent::create([
            'user_id' => $user->id,
            'task_occurrence_id' => $session->occurrence_id,
            'event_type' => 'timer_paused',
            'event_time' => $now,
            'metadata' => [
                'session_id' => $session->id,
                'elapsed_seconds' => $session->elapsed_seconds,
            ],
        ]);

        return $session;
    }

    /**
     * Resume a paused timer session.
     */
    public function resumeSession(TimerSession $session, User $user): TimerSession
    {
        if ($session->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized timer session');
        }

        if ($session->status !== TimerSession::STATUS_PAUSED) {
            return $session;
        }

        $now = Carbon::now('UTC');
        $session->started_at = $now; // Reset running segment anchor
        $session->paused_at = null;
        $session->status = TimerSession::STATUS_RUNNING;
        $session->save();

        ActivityEvent::create([
            'user_id' => $user->id,
            'task_occurrence_id' => $session->occurrence_id,
            'event_type' => 'timer_resumed',
            'event_time' => $now,
            'metadata' => [
                'session_id' => $session->id,
                'elapsed_seconds' => $session->elapsed_seconds,
            ],
        ]);

        return $session;
    }

    /**
     * Complete a timer session.
     */
    public function completeSession(TimerSession $session, User $user): TimerSession
    {
        if ($session->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized timer session');
        }

        $now = Carbon::now('UTC');

        // If currently running, add final segment
        if ($session->status === TimerSession::STATUS_RUNNING && $session->started_at) {
            $session->elapsed_seconds += (int) $session->started_at->diffInSeconds($now);
        }

        $session->completed_at = $now;
        $session->status = TimerSession::STATUS_COMPLETED;
        $session->save();

        ActivityEvent::create([
            'user_id' => $user->id,
            'task_occurrence_id' => $session->occurrence_id,
            'event_type' => 'timer_completed',
            'event_time' => $now,
            'metadata' => [
                'session_id' => $session->id,
                'elapsed_seconds' => $session->elapsed_seconds,
            ],
        ]);

        return $session;
    }
}
