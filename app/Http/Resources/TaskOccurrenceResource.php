<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskOccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completions = $this->whenLoaded('stepCompletions');
        $totalSteps = 0;
        $completedSteps = 0;

        if ($this->relationLoaded('routine') && $this->routine && $this->routine->relationLoaded('steps')) {
            $totalSteps = $this->routine->steps->count();
        } elseif ($this->relationLoaded('stepCompletions')) {
            $totalSteps = $this->stepCompletions->count();
        }

        if ($this->relationLoaded('stepCompletions')) {
            $completedSteps = $this->stepCompletions->whereIn('status', ['completed', 'overridden'])->count();
        }

        return [
            'id' => $this->id,
            'routine_id' => $this->routine_id,
            'schedule_id' => $this->schedule_id,
            'due_at' => $this->due_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'status' => $this->status,
            'actual_duration' => $this->actual_duration,
            'notes' => $this->notes,
            'routine' => new RoutineResource($this->whenLoaded('routine')),
            'schedule' => new RoutineScheduleResource($this->whenLoaded('schedule')),
            'step_completions' => StepCompletionResource::collection($this->whenLoaded('stepCompletions')),
            'progress' => [
                'total_steps' => $totalSteps,
                'completed_steps' => $completedSteps,
                'percentage' => $totalSteps > 0 ? (int) round(($completedSteps / $totalSteps) * 100) : 0,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
