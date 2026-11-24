<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceSubmission extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_CAMERA = 'camera';
    public const TYPE_UPLOAD = 'upload';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPE_TIMER = 'timer';
    public const TYPE_COUNTER = 'counter';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RULE_SATISFIED = 'rule_satisfied';
    public const STATUS_SELF_REPORTED = 'self_reported';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DELETED = 'deleted';
    public const STATUS_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'user_id',
        'occurrence_id',
        'routine_step_id',
        'evidence_type',
        'storage_path',
        'mime_type',
        'file_size',
        'verification_status',
        'validation_source',
        'metadata',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class, 'occurrence_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(RoutineStep::class, 'routine_step_id');
    }
}
