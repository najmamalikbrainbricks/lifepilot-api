<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProtectedBlockRequest;
use App\Models\ProtectedTimeBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectedTimeBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = $request->user()->protectedTimeBlocks()->orderBy('start_time')->get();

        return response()->json([
            'protected_blocks' => $blocks,
        ]);
    }

    public function store(StoreProtectedBlockRequest $request): JsonResponse
    {
        $block = $request->user()->protectedTimeBlocks()->create($request->validated());

        return response()->json([
            'message' => 'Protected focus block created',
            'protected_block' => $block,
        ], 201);
    }

    public function update(StoreProtectedBlockRequest $request, ProtectedTimeBlock $protectedTimeBlock): JsonResponse
    {
        if ($protectedTimeBlock->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $protectedTimeBlock->update($request->validated());

        return response()->json([
            'message' => 'Protected focus block updated',
            'protected_block' => $protectedTimeBlock,
        ]);
    }

    public function destroy(Request $request, ProtectedTimeBlock $protectedTimeBlock): JsonResponse
    {
        if ($protectedTimeBlock->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $protectedTimeBlock->delete();

        return response()->json([
            'message' => 'Protected focus block deleted',
        ]);
    }
}
