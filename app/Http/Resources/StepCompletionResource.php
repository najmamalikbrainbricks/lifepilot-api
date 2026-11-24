<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StepCompletionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurrence_id' => $this->occurrence_id,
            'routine_step_id' => $this->routine_step_id,
            'step' => new RoutineStepResource($this->whenLoaded('step')),
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
