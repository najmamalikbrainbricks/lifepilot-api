<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteOccurrenceRequest;
use App\Http\Requests\CompleteStepRequest;
use App\Http\Requests\SkipStepRequest;
use App\Http\Resources\StepCompletionResource;
use App\Http\Resources\TaskOccurrenceResource;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Services\RoutineSchedulerService;
use App\Services\TaskExecutionService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TaskOccurrenceController extends Controller
{
    public function __construct(
        protected RoutineSchedulerService $schedulerService,
        protected TaskExecutionService $executionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userTz = $user->timezone ?: 'UTC';

        // Optional date filter, defaults to today in user's timezone
        $dateParam = $request->query('date');
        $targetDate = $dateParam ? Carbon::parse($dateParam, $userTz) : Carbon::now($userTz);

        $startOfDay = $targetDate->copy()->startOfDay()->utc();
        $endOfDay = $targetDate->copy()->endOfDay()->utc();

        // Ensure occurrences for this day are generated
        $this->schedulerService->generateForUser($user, $targetDate->copy()->startOfDay(), $targetDate->copy()->endOfDay());

        $query = TaskOccurrence::whereHas('routine', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereBetween('due_at', [$startOfDay, $endOfDay])
        ->with(['routine.steps', 'routine.alarmRule', 'schedule', 'stepCompletions.step']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($routineId = $request->query('routine_id')) {
            $query->where('routine_id', $routineId);
        }

        $occurrences = $query->orderBy('due_at')->get();

        return response()->json([
            'date' => $targetDate->format('Y-m-d'),
            'timezone' => $userTz,
            'occurrences' => TaskOccurrenceResource::collection($occurrences),
        ]);
    }

    public function show(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'occurrence' => new TaskOccurrenceResource($occurrence->load(['routine.steps', 'routine.alarmRule', 'schedule', 'stepCompletions.step'])),
        ]);
    }

    public function start(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->executionService->startOccurrence($occurrence, $request->user());

        return response()->json([
            'message' => 'Task occurrence started',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function completeStep(CompleteStepRequest $request, TaskOccurrence $occurrence, RoutineStep $step): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id || $step->routine_id !== $occurrence->routine_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $completion = $this->executionService->completeStep(
            $occurrence,
            $step,
            $request->validated('notes'),
            $request->user()
        );

        return response()->json([
            'message' => 'Step completed',
            'completion' => new StepCompletionResource($completion->load('step')),
            'occurrence' => new TaskOccurrenceResource($occurrence->fresh(['routine.steps', 'stepCompletions.step'])),
        ]);
    }

    public function skipStep(SkipStepRequest $request, TaskOccurrence $occurrence, RoutineStep $step): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id || $step->routine_id !== $occurrence->routine_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $completion = $this->executionService->skipStep(
                $occurrence,
                $step,
                (bool) $request->validated('override'),
                $request->validated('reason'),
                $request->user()
            );

            return response()->json([
                'message' => 'Step skipped',
                'completion' => new StepCompletionResource($completion->load('step')),
                'occurrence' => new TaskOccurrenceResource($occurrence->fresh(['routine.steps', 'stepCompletions.step'])),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'requires_override' => true,
            ], 422);
        }
    }

    public function complete(CompleteOccurrenceRequest $request, TaskOccurrence $occurrence): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $updated = $this->executionService->completeOccurrence(
                $occurrence,
                (bool) $request->validated('force'),
                $request->validated('notes'),
                $request->user()
            );

            return response()->json([
                'message' => 'Task occurrence completed',
                'occurrence' => new TaskOccurrenceResource($updated),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'incomplete_required_steps' => true,
            ], 422);
        }
    }

    public function skip(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->executionService->skipOccurrence(
            $occurrence,
            $request->input('notes'),
            $request->user()
        );

        return response()->json([
            'message' => 'Task occurrence skipped',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function pause(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        if ($occurrence->routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->executionService->pauseOccurrence($occurrence);

        return response()->json([
            'message' => 'Task occurrence paused',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }
}
