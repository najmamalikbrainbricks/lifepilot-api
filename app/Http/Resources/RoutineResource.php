<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'estimated_duration' => $this->estimated_duration,
            'icon' => $this->icon,
            'color' => $this->color,
            'enabled' => (bool) $this->enabled,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'steps' => RoutineStepResource::collection($this->whenLoaded('steps')),
            'schedules' => RoutineScheduleResource::collection($this->whenLoaded('schedules')),
            'alarm_rule' => new AlarmRuleResource($this->whenLoaded('alarmRule')),
            'stats' => [
                'total_occurrences' => $this->occurrences_count ?? ($this->relationLoaded('occurrences') ? $this->occurrences->count() : 0),
                'completed_occurrences' => $this->completed_occurrences_count ?? ($this->relationLoaded('occurrences') ? $this->occurrences->whereIn('status', ['completed', 'partially_completed'])->count() : 0),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
