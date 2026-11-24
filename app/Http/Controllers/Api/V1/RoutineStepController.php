<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderRoutineStepsRequest;
use App\Http\Requests\StoreRoutineStepRequest;
use App\Http\Resources\RoutineStepResource;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoutineStepController extends Controller
{
    public function index(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'steps' => RoutineStepResource::collection($routine->steps),
        ]);
    }

    public function store(StoreRoutineStepRequest $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validated();
        if (! isset($data['sort_order'])) {
            $data['sort_order'] = $routine->steps()->max('sort_order') + 1;
        }

        $step = $routine->steps()->create($data);

        return response()->json([
            'message' => 'Step created successfully',
            'step' => new RoutineStepResource($step),
        ], 201);
    }

    public function update(StoreRoutineStepRequest $request, Routine $routine, RoutineStep $step): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id || $step->routine_id !== $routine->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $step->update($request->validated());

        return response()->json([
            'message' => 'Step updated successfully',
            'step' => new RoutineStepResource($step),
        ]);
    }

    public function destroy(Request $request, Routine $routine, RoutineStep $step): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id || $step->routine_id !== $routine->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $step->delete();

        return response()->json([
            'message' => 'Step deleted successfully',
        ]);
    }

    public function reorder(ReorderRoutineStepsRequest $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stepIds = $request->step_ids;

        DB::transaction(function () use ($routine, $stepIds) {
            foreach ($stepIds as $order => $id) {
                $routine->steps()->where('id', $id)->update(['sort_order' => $order]);
            }
        });

        return response()->json([
            'message' => 'Steps reordered successfully',
            'steps' => RoutineStepResource::collection($routine->steps()->orderBy('sort_order')->get()),
        ]);
    }
}
