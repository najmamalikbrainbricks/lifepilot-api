<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Resources\WorkShiftResource;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shifts = $request->user()->workShifts()->orderBy('created_at')->get();

        return response()->json([
            'work_shifts' => WorkShiftResource::collection($shifts),
        ]);
    }

    public function store(StoreWorkShiftRequest $request): JsonResponse
    {
        $data = $request->validated();
        // Detect overnight automatically if end_time < start_time
        if (! isset($data['overnight'])) {
            $data['overnight'] = ($data['end_time'] < $data['start_time']);
        }

        $shift = $request->user()->workShifts()->create($data);

        return response()->json([
            'message' => 'Work shift created successfully',
            'work_shift' => new WorkShiftResource($shift),
        ], 201);
    }

    public function update(StoreWorkShiftRequest $request, WorkShift $workShift): JsonResponse
    {
        if ($workShift->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        if (! isset($data['overnight'])) {
            $data['overnight'] = ($data['end_time'] < $data['start_time']);
        }

        $workShift->update($data);

        return response()->json([
            'message' => 'Work shift updated successfully',
            'work_shift' => new WorkShiftResource($workShift),
        ]);
    }

    public function destroy(Request $request, WorkShift $workShift): JsonResponse
    {
        if ($workShift->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $workShift->delete();

        return response()->json([
            'message' => 'Work shift deleted successfully',
        ]);
    }
}
