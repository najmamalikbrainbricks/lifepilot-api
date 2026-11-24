<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'time_format',
        'reminder_preferences',
        'sleep_goal',
        'bedtime',
        'wake_time',
        'focus_goal',
        'focus_category',
        'activity_preferences',
    ];

    protected function casts(): array
    {
        return [
            'reminder_preferences' => 'array',
            'activity_preferences' => 'array',
            'sleep_goal' => 'float',
            'focus_goal' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
