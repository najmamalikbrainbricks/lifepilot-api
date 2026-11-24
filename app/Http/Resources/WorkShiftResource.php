<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'job_name' => $this->job_name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'days_of_week' => $this->days_of_week ?? [],
            'fixed_or_flexible' => $this->fixed_or_flexible,
            'overnight' => (bool) $this->overnight,
            'enabled' => (bool) $this->enabled,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
