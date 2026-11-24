<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskOccurrenceResource;
use App\Models\TaskOccurrence;
use App\Services\AlarmWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlarmWorkflowController extends Controller
{
    public function __construct(
        protected AlarmWorkflowService $alarmService
    ) {}

    public function due(Request $request): JsonResponse
    {
        $user = $request->user();
        $dueOccurrences = $this->alarmService->getDueAlarms($user);

        return response()->json([
            'count' => $dueOccurrences->count(),
            'due_alarms' => TaskOccurrenceResource::collection($dueOccurrences),
        ]);
    }

    public function trigger(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->alarmService->triggerRinging($occurrence, $user);

        return response()->json([
            'message' => 'Alarm triggered ringing',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function acknowledge(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->alarmService->acknowledgeAlarm($occurrence, $user);

        return response()->json([
            'message' => 'Alarm acknowledged',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function snooze(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $minutes = $request->input('minutes');
        $updated = $this->alarmService->snoozeAlarm($occurrence, $minutes ? (int) $minutes : null, $user);

        return response()->json([
            'message' => 'Alarm snoozed',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function dismiss(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();
        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reason = $request->input('reason');
        $updated = $this->alarmService->dismissAlarm($occurrence, $user, $reason);

        return response()->json([
            'message' => 'Alarm dismissed',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function emergencyStop(Request $request): JsonResponse
    {
        $user = $request->user();
        $stoppedCount = $this->alarmService->emergencyStop($user);

        return response()->json([
            'message' => 'Emergency stop executed',
            'stopped_count' => $stoppedCount,
        ]);
    }
}
