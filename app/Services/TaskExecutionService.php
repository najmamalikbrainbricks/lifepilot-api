<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\RoutineStep;
use App\Models\StepCompletion;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskExecutionService
{
    /**
     * Start an occurrence.
     * Transitions status from scheduled/ready to in_progress.
     * Initializes step completions if not already created.
     */
    public function startOccurrence(TaskOccurrence $occurrence, ?User $user = null): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $user) {
            $now = Carbon::now();

            $occurrence->status = TaskOccurrence::STATUS_IN_PROGRESS;
            if (! $occurrence->started_at) {
                $occurrence->started_at = $now;
            }
            $occurrence->save();

            // Initialize step completion records for all steps of this routine
            $routine = $occurrence->routine()->with('steps')->first();
            if ($routine && $routine->steps) {
                foreach ($routine->steps as $step) {
                    StepCompletion::firstOrCreate(
                        [
                            'occurrence_id' => $occurrence->id,
                            'routine_step_id' => $step->id,
                        ],
                        [
                            'status' => StepCompletion::STATUS_PENDING,
                        ]
                    );
                }
            }

            // Log activity event
            $userId = $user?->id ?? $occurrence->routine->user_id;
            ActivityEvent::create([
                'user_id' => $userId,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'routine_started',
                'event_time' => $now,
                'metadata' => [
                    'routine_title' => $routine?->title,
                    'due_at' => $occurrence->due_at->toIso8601String(),
                ],
            ]);

            return $occurrence->fresh(['stepCompletions.step', 'routine.steps']);
        });
    }

    /**
     * Mark a step as complete.
     */
    public function completeStep(TaskOccurrence $occurrence, RoutineStep $step, ?string $notes = null, ?User $user = null): StepCompletion
    {
        return DB::transaction(function () use ($occurrence, $step, $notes, $user) {
            $completion = StepCompletion::firstOrCreate(
                [
                    'occurrence_id' => $occurrence->id,
                    'routine_step_id' => $step->id,
                ],
                [
                    'status' => StepCompletion::STATUS_PENDING,
                ]
            );

            $now = Carbon::now();
            $completion->status = StepCompletion::STATUS_COMPLETED;
            $completion->completed_at = $now;
            if ($notes) {
                $completion->notes = $notes;
            }
            $completion->save();

            // If occurrence is not yet in progress, mark in_progress
            if ($occurrence->status === TaskOccurrence::STATUS_SCHEDULED || $occurrence->status === TaskOccurrence::STATUS_READY) {
                $occurrence->status = TaskOccurrence::STATUS_IN_PROGRESS;
                $occurrence->started_at = $now;
                $occurrence->save();
            }

            // Log activity event
            $userId = $user?->id ?? $occurrence->routine->user_id;
            ActivityEvent::create([
                'user_id' => $userId,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'step_completed',
                'event_time' => $now,
                'metadata' => [
                    'step_id' => $step->id,
                    'step_title' => $step->title,
                    'sort_order' => $step->sort_order,
                ],
            ]);

            return $completion;
        });
    }

    /**
     * Skip a step. Required steps cannot be skipped unless explicit override is true.
     */
    public function skipStep(TaskOccurrence $occurrence, RoutineStep $step, bool $override = false, ?string $reason = null, ?User $user = null): StepCompletion
    {
        if ($step->required && ! $override) {
            throw new InvalidArgumentException("Step '{$step->title}' is required and cannot be skipped without explicit override.");
        }

        return DB::transaction(function () use ($occurrence, $step, $override, $reason, $user) {
            $completion = StepCompletion::firstOrCreate(
                [
                    'occurrence_id' => $occurrence->id,
                    'routine_step_id' => $step->id,
                ],
                [
                    'status' => StepCompletion::STATUS_PENDING,
                ]
            );

            $now = Carbon::now();
            $completion->status = $override ? StepCompletion::STATUS_OVERRIDDEN : StepCompletion::STATUS_SKIPPED;
            $completion->completed_at = $now;
            $completion->notes = $reason ?: ($override ? 'Required step overridden by user' : 'Optional step skipped');
            $completion->save();

            $userId = $user?->id ?? $occurrence->routine->user_id;
            ActivityEvent::create([
                'user_id' => $userId,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => $override ? 'step_overridden' : 'step_skipped',
                'event_time' => $now,
                'metadata' => [
                    'step_id' => $step->id,
                    'step_title' => $step->title,
                    'override' => $override,
                    'reason' => $reason,
                ],
            ]);

            return $completion;
        });
    }

    /**
     * Complete the routine occurrence.
     * Enforces that all required steps are completed or overridden, unless $force is true.
     */
    public function completeOccurrence(TaskOccurrence $occurrence, bool $force = false, ?string $notes = null, ?User $user = null): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $force, $notes, $user) {
            $routine = $occurrence->routine()->with('steps')->first();
            $steps = $routine ? $routine->steps : collect();

            // Load or initialize completions
            $completions = StepCompletion::where('occurrence_id', $occurrence->id)->get()->keyBy('routine_step_id');

            $uncompletedRequired = [];
            $hasSkippedOptional = false;

            foreach ($steps as $step) {
                $comp = $completions->get($step->id);
                $status = $comp ? $comp->status : StepCompletion::STATUS_PENDING;

                if ($step->required) {
                    if ($status !== StepCompletion::STATUS_COMPLETED && $status !== StepCompletion::STATUS_OVERRIDDEN) {
                        $uncompletedRequired[] = $step->title;
                    }
                } else {
                    if ($status === StepCompletion::STATUS_SKIPPED || $status === StepCompletion::STATUS_PENDING) {
                        $hasSkippedOptional = true;
                    }
                }
            }

            if (! empty($uncompletedRequired) && ! $force) {
                $list = implode(', ', $uncompletedRequired);
                throw new InvalidArgumentException("Cannot complete routine: required steps are not finished: {$list}. Complete them or provide explicit override.");
            }

            $now = Carbon::now();
            $occurrence->completed_at = $now;
            if ($notes) {
                $occurrence->notes = $notes;
            }

            // Duration in seconds
            if ($occurrence->started_at) {
                $occurrence->actual_duration = $now->diffInSeconds($occurrence->started_at);
            }

            // Status: if all steps are completed (no skipped optional), then completed; if optional skipped, partially_completed
            $occurrence->status = (! empty($uncompletedRequired) || $hasSkippedOptional)
                ? TaskOccurrence::STATUS_PARTIALLY_COMPLETED
                : TaskOccurrence::STATUS_COMPLETED;

            $occurrence->save();

            // Log activity event
            $userId = $user?->id ?? $occurrence->routine->user_id;
            ActivityEvent::create([
                'user_id' => $userId,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'routine_completed',
                'event_time' => $now,
                'metadata' => [
                    'routine_title' => $routine?->title,
                    'status' => $occurrence->status,
                    'actual_duration_seconds' => $occurrence->actual_duration,
                ],
            ]);

            return $occurrence->fresh(['stepCompletions.step', 'routine.steps']);
        });
    }

    /**
     * Skip the routine occurrence.
     */
    public function skipOccurrence(TaskOccurrence $occurrence, ?string $notes = null, ?User $user = null): TaskOccurrence
    {
        return DB::transaction(function () use ($occurrence, $notes, $user) {
            $now = Carbon::now();
            $occurrence->status = TaskOccurrence::STATUS_SKIPPED;
            $occurrence->completed_at = $now;
            if ($notes) {
                $occurrence->notes = $notes;
            }
            $occurrence->save();

            $userId = $user?->id ?? $occurrence->routine->user_id;
            ActivityEvent::create([
                'user_id' => $userId,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'routine_skipped',
                'event_time' => $now,
                'metadata' => [
                    'notes' => $notes,
                ],
            ]);

            return $occurrence;
        });
    }

    /**
     * Pause an in-progress occurrence.
     */
    public function pauseOccurrence(TaskOccurrence $occurrence): TaskOccurrence
    {
        if ($occurrence->status === TaskOccurrence::STATUS_IN_PROGRESS) {
            $occurrence->status = TaskOccurrence::STATUS_READY;
            $occurrence->save();
        }
        return $occurrence;
    }
}
