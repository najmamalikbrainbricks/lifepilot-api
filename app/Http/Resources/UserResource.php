<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'status' => $this->status,
            'preference' => new UserPreferenceResource($this->whenLoaded('preference')),
            'goals' => GoalResource::collection($this->whenLoaded('goals')),
            'work_shifts' => WorkShiftResource::collection($this->whenLoaded('workShifts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
