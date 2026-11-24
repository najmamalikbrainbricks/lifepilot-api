<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Routine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'priority',
        'estimated_duration',
        'icon',
        'color',
        'enabled',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_duration' => 'integer',
            'enabled' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RoutineStep::class)->orderBy('sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RoutineSchedule::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(TaskOccurrence::class);
    }

    public function alarmRule(): HasOne
    {
        return $this->hasOne(AlarmRule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true)->whereNull('archived_at');
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('enabled', false)->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }
}
