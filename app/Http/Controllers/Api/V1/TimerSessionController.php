<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Models\TimerSession;
use App\Services\TimerSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TimerSessionController extends Controller
{
    public function __construct(
        protected TimerSessionService $timerService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'occurrence_id' => 'required|exists:task_occurrences,id',
            'routine_step_id' => 'nullable|exists:routine_steps,id',
            'target_duration_seconds' => 'nullable|integer|min:1',
            'metadata' => 'nullable|array',
        ]);

        $occurrence = TaskOccurrence::findOrFail($request->input('occurrence_id'));
        $step = $request->input('routine_step_id') ? RoutineStep::find($request->input('routine_step_id')) : null;

        try {
            $session = $this->timerService->startSession(
                $user,
                $occurrence,
                $step,
                $request->input('target_duration_seconds'),
                $request->input('metadata', [])
            );

            return response()->json([
                'message' => 'Timer session started',
                'timer_session' => $session,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pause(Request $request, TimerSession $timerSession): JsonResponse
    {
        $user = $request->user();

        try {
            $updated = $this->timerService->pauseSession($timerSession, $user);

            return response()->json([
                'message' => 'Timer session paused',
                'timer_session' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function resume(Request $request, TimerSession $timerSession): JsonResponse
    {
        $user = $request->user();

        try {
            $updated = $this->timerService->resumeSession($timerSession, $user);

            return response()->json([
                'message' => 'Timer session resumed',
                'timer_session' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function complete(Request $request, TimerSession $timerSession): JsonResponse
    {
        $user = $request->user();

        try {
            $updated = $this->timerService->completeSession($timerSession, $user);

            return response()->json([
                'message' => 'Timer session completed',
                'timer_session' => $updated,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
