<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskOccurrenceResource;
use App\Models\TaskOccurrence;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyReportController extends Controller
{
    public function __construct(
        protected RoutineSchedulerService $schedulerService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userTz = $user->timezone ?: 'Asia/Karachi';
        $today = Carbon::now($userTz);

        $startOfDay = $today->copy()->startOfDay()->utc();
        $endOfDay = $today->copy()->endOfDay()->utc();

        // Ensure occurrences are generated for today
        $this->schedulerService->generateForUser($user, $today->copy()->startOfDay(), $today->copy()->endOfDay());

        $occurrences = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereBetween('due_at', [$startOfDay, $endOfDay])
        ->with(['routine.steps', 'routine.alarmRule', 'schedule', 'stepCompletions.step'])
        ->orderBy('due_at')
        ->get();

        $total = $occurrences->count();
        $completed = $occurrences->whereIn('status', [TaskOccurrence::STATUS_COMPLETED, TaskOccurrence::STATUS_PARTIALLY_COMPLETED])->count();
        $inProgress = $occurrences->where('status', TaskOccurrence::STATUS_IN_PROGRESS)->first();
        $upcoming = $occurrences->whereIn('status', [TaskOccurrence::STATUS_SCHEDULED, TaskOccurrence::STATUS_READY])->first();
        $missed = $occurrences->where('status', TaskOccurrence::STATUS_MISSED)->count();
        $pending = $occurrences->whereIn('status', [TaskOccurrence::STATUS_SCHEDULED, TaskOccurrence::STATUS_READY, TaskOccurrence::STATUS_IN_PROGRESS])->count();

        $completionPercent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $pref = $user->preference;

        return response()->json([
            'date' => $today->format('Y-m-d'),
            'date_formatted' => $today->format('l, F j'),
            'timezone' => $userTz,
            'summary' => [
                'total_routines' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'missed' => $missed,
                'completion_percent' => $completionPercent,
                'focus_goal_minutes' => $pref ? $pref->focus_goal : 60,
                'focus_category' => $pref ? $pref->focus_category : 'Agency',
            ],
            'current_task' => $inProgress ? new TaskOccurrenceResource($inProgress) : null,
            'next_task' => $upcoming ? new TaskOccurrenceResource($upcoming) : null,
            'occurrences' => TaskOccurrenceResource::collection($occurrences),
        ]);
    }
}
