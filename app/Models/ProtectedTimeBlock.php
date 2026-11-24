<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectedTimeBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'category',
        'start_time',
        'end_time',
        'days_of_week',
        'minimum_duration_minutes',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'minimum_duration_minutes' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
