<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'theme' => $this->theme,
            'time_format' => $this->time_format,
            'reminder_preferences' => $this->reminder_preferences ?? [],
            'sleep_goal' => $this->sleep_goal,
            'bedtime' => $this->bedtime,
            'wake_time' => $this->wake_time,
            'focus_goal' => $this->focus_goal,
            'focus_category' => $this->focus_category,
            'activity_preferences' => $this->activity_preferences ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
