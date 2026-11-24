<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlarmRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_id' => $this->routine_id,
            'enabled' => (bool) $this->enabled,
            'sound' => $this->sound,
            'repeat_interval' => $this->repeat_interval,
            'max_attempts' => $this->max_attempts,
            'snooze_duration' => $this->snooze_duration,
            'verification_required' => (bool) $this->verification_required,
            'escalation_preference' => $this->escalation_preference,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
