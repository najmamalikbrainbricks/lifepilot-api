<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoutineScheduleRequest;
use App\Http\Resources\RoutineScheduleResource;
use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutineScheduleController extends Controller
{
    public function __construct(
        protected RoutineSchedulerService $schedulerService
    ) {}

    public function index(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'schedules' => RoutineScheduleResource::collection($routine->schedules),
        ]);
    }

    public function store(StoreRoutineScheduleRequest $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        if (empty($data['timezone'])) {
            $data['timezone'] = $request->user()->timezone ?: 'UTC';
        }

        $schedule = $routine->schedules()->create($data);

        // Auto-generate occurrences for today
        $userTz = $request->user()->timezone ?: 'UTC';
        $today = Carbon::now($userTz)->startOfDay();
        $this->schedulerService->generateForSchedule($schedule, $today, $today);

        return response()->json([
            'message' => 'Schedule created successfully',
            'schedule' => new RoutineScheduleResource($schedule),
        ], 201);
    }

    public function update(StoreRoutineScheduleRequest $request, Routine $routine, RoutineSchedule $schedule): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id || $schedule->routine_id !== $routine->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        $schedule->update($data);

        return response()->json([
            'message' => 'Schedule updated successfully',
            'schedule' => new RoutineScheduleResource($schedule),
        ]);
    }

    public function destroy(Request $request, Routine $routine, RoutineSchedule $schedule): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id || $schedule->routine_id !== $routine->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }
}
