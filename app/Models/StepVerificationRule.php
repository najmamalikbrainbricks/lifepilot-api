<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepVerificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'routine_step_id',
        'verification_type',
        'required',
        'minimum_duration',
        'minimum_count',
        'allow_gallery',
        'fallback_type',
        'rule_configuration',
    ];

    protected $casts = [
        'required' => 'boolean',
        'minimum_duration' => 'integer',
        'minimum_count' => 'integer',
        'allow_gallery' => 'boolean',
        'rule_configuration' => 'array',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(RoutineStep::class, 'routine_step_id');
    }
}
