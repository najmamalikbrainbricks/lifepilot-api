<?php

namespace App\Services;

use App\Models\EvidenceSubmission;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountabilityService
{
    /**
     * Get detailed accountability history records.
     */
    public function getAccountabilityHistory(User $user, int $limit = 50): Collection
    {
        $tz = $user->timezone ?: 'Asia/Karachi';

        $occurrences = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereIn('status', [
            TaskOccurrence::STATUS_COMPLETED,
            TaskOccurrence::STATUS_PARTIALLY_COMPLETED,
            TaskOccurrence::STATUS_MISSED,
            TaskOccurrence::STATUS_SKIPPED,
        ])
        ->with(['routine', 'stepCompletions.step', 'evidenceSubmissions', 'alarmEvents'])
        ->orderByDesc('due_at')
        ->limit($limit)
        ->get();

        return $occurrences->map(function (TaskOccurrence $occ) use ($tz) {
            $dueLocal = $occ->due_at->copy()->timezone($tz);
            $startLocal = $occ->started_at?->copy()->timezone($tz);
            $completedLocal = $occ->completed_at?->copy()->timezone($tz);

            // Compute delay minutes
            $delayMinutes = 0;
            if ($occ->started_at && $occ->due_at) {
                $delayMinutes = max(0, (int) round($occ->due_at->diffInMinutes($occ->started_at, false)));
            }

            $hasVerifiedEvidence = $occ->evidenceSubmissions->whereIn('verification_status', [
                EvidenceSubmission::STATUS_RULE_SATISFIED,
                EvidenceSubmission::STATUS_SUBMITTED,
            ])->isNotEmpty();

            return [
                'id' => $occ->id,
                'routine_title' => $occ->routine->title,
                'category' => $occ->routine->category,
                'priority' => $occ->routine->priority,
                'scheduled_due_at' => $dueLocal->format('Y-m-d H:i'),
                'started_at' => $startLocal?->format('H:i'),
                'completed_at' => $completedLocal?->format('H:i'),
                'delay_minutes' => $delayMinutes,
                'status' => $occ->status,
                'is_verified' => $hasVerifiedEvidence,
                'alarm_acknowledgements' => $occ->alarmEvents->where('event_type', 'acknowledged')->count(),
                'reminders_count' => $occ->alarm_attempts,
                'steps_count' => $occ->stepCompletions->count(),
                'completed_steps' => $occ->stepCompletions->where('status', 'completed')->count(),
                'evidence_items' => $occ->evidenceSubmissions->map(function ($ev) {
                    return [
                        'id' => $ev->id,
                        'type' => $ev->evidence_type,
                        'status' => $ev->verification_status,
                        'source' => $ev->validation_source,
                        'has_file' => ! empty($ev->storage_path),
                        'submitted_at' => $ev->submitted_at?->toIso8601String(),
                    ];
                }),
                'notes' => $occ->notes,
            ];
        });
    }

    /**
     * Compute aggregated metrics for daily/weekly accountability report.
     */
    public function getAccountabilitySummary(User $user, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $tz = $user->timezone ?: 'Asia/Karachi';
        $now = Carbon::now($tz);

        $startUtc = ($start ?: $now->copy()->subDays(7)->startOfDay())->utc();
        $endUtc = ($end ?: $now->copy()->endOfDay())->utc();

        $occurrences = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereBetween('due_at', [$startUtc, $endUtc])
        ->with(['routine', 'evidenceSubmissions'])
        ->get();

        $completed = $occurrences->whereIn('status', [
            TaskOccurrence::STATUS_COMPLETED,
            TaskOccurrence::STATUS_PARTIALLY_COMPLETED,
        ]);
        $missed = $occurrences->where('status', TaskOccurrence::STATUS_MISSED);

        // Verified vs Self-reported
        $verifiedCount = 0;
        $selfReportedCount = 0;
        $totalDelayMinutes = 0;
        $delayCount = 0;
        $categoryBreakdown = [];

        foreach ($completed as $occ) {
            $hasEvidence = $occ->evidenceSubmissions->whereIn('verification_status', [
                EvidenceSubmission::STATUS_RULE_SATISFIED,
                EvidenceSubmission::STATUS_SUBMITTED,
            ])->isNotEmpty();

            if ($hasEvidence) {
                $verifiedCount++;
            } else {
                $selfReportedCount++;
            }

            if ($occ->started_at && $occ->due_at) {
                $diff = (int) round($occ->due_at->diffInMinutes($occ->started_at, false));
                if ($diff > 0) {
                    $totalDelayMinutes += $diff;
                    $delayCount++;
                }
            }

            $cat = $occ->routine->category ?: 'Personal';
            if (! isset($categoryBreakdown[$cat])) {
                $categoryBreakdown[$cat] = [
                    'category' => $cat,
                    'count' => 0,
                    'estimated_minutes' => 0,
                    'actual_seconds' => 0,
                ];
            }
            $categoryBreakdown[$cat]['count']++;
            $categoryBreakdown[$cat]['estimated_minutes'] += ($occ->routine->estimated_duration ?: 15);
            $categoryBreakdown[$cat]['actual_seconds'] += ($occ->actual_duration ?: 0);
        }

        $avgDelay = $delayCount > 0 ? (int) round($totalDelayMinutes / $delayCount) : 0;

        // Recent evidence submissions
        $recentEvidence = EvidenceSubmission::where('user_id', $user->id)
            ->with(['step', 'occurrence.routine'])
            ->orderByDesc('submitted_at')
            ->limit(12)
            ->get()
            ->map(function (EvidenceSubmission $ev) {
                return [
                    'id' => $ev->id,
                    'step_title' => $ev->step->title ?? 'Step',
                    'routine_title' => $ev->occurrence->routine->title ?? 'Routine',
                    'evidence_type' => $ev->evidence_type,
                    'verification_status' => $ev->verification_status,
                    'validation_source' => $ev->validation_source,
                    'has_image' => ! empty($ev->storage_path),
                    'file_size' => $ev->file_size,
                    'metadata' => $ev->metadata,
                    'submitted_at' => $ev->submitted_at?->toIso8601String(),
                ];
            });

        return [
            'period_start' => $startUtc->copy()->timezone($tz)->format('Y-m-d'),
            'period_end' => $endUtc->copy()->timezone($tz)->format('Y-m-d'),
            'timezone' => $tz,
            'metrics' => [
                'total_scheduled' => $occurrences->count(),
                'completed_routines' => $completed->count(),
                'missed_routines' => $missed->count(),
                'verified_completions' => $verifiedCount,
                'self_reported_completions' => $selfReportedCount,
                'average_delay_minutes' => $avgDelay,
                'completion_rate' => $occurrences->count() > 0 ? (int) round(($completed->count() / $occurrences->count()) * 100) : 100,
            ],
            'category_breakdown' => array_values($categoryBreakdown),
            'recent_evidence' => $recentEvidence,
        ];
    }
}
