<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RoutineStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_id',
        'title',
        'description',
        'sort_order',
        'estimated_duration',
        'required',
        'verification_type',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'estimated_duration' => 'integer',
            'required' => 'boolean',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(StepCompletion::class);
    }

    public function verificationRule(): HasOne
    {
        return $this->hasOne(StepVerificationRule::class, 'routine_step_id');
    }

    public function evidenceSubmissions(): HasMany
    {
        return $this->hasMany(EvidenceSubmission::class, 'routine_step_id');
    }

    public function timerSessions(): HasMany
    {
        return $this->hasMany(TimerSession::class, 'routine_step_id');
    }
}
