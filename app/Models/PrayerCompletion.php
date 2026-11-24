<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrayerCompletion extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_MISSED = 'missed';
    public const STATUS_EXCUSED = 'excused';

    protected $fillable = [
        'user_id',
        'prayer_name',
        'date',
        'status',
        'completed_at',
        'on_time',
        'in_jamaat',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'on_time' => 'boolean',
            'in_jamaat' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
