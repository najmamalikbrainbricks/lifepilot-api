<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = $request->user()->goals()->orderBy('priority')->get();

        return response()->json([
            'goals' => GoalResource::collection($goals),
        ]);
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($request->validated());

        return response()->json([
            'message' => 'Goal created successfully',
            'goal' => new GoalResource($goal),
        ], 201);
    }

    public function update(StoreGoalRequest $request, Goal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $goal->update($request->validated());

        return response()->json([
            'message' => 'Goal updated successfully',
            'goal' => new GoalResource($goal),
        ]);
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        if ($goal->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $goal->delete();

        return response()->json([
            'message' => 'Goal deleted successfully',
        ]);
    }
}
