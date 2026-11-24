<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmEvent extends Model
{
    use HasFactory;

    public const EVENT_DUE = 'due';
    public const EVENT_RINGING = 'ringing';
    public const EVENT_ACKNOWLEDGED = 'acknowledged';
    public const EVENT_SNOOZED = 'snoozed';
    public const EVENT_DISMISSED = 'dismissed';
    public const EVENT_AUTO_MISSED = 'auto_missed';
    public const EVENT_EMERGENCY_STOP = 'emergency_stop';

    protected $fillable = [
        'user_id',
        'occurrence_id',
        'alarm_rule_id',
        'event_type',
        'event_time',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class, 'occurrence_id');
    }

    public function alarmRule(): BelongsTo
    {
        return $this->belongsTo(AlarmRule::class);
    }
}
