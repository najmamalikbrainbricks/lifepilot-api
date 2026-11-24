<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_name',
        'start_time',
        'end_time',
        'days_of_week',
        'fixed_or_flexible',
        'overnight',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'overnight' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
