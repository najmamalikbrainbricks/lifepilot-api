<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlarmRuleRequest;
use App\Http\Resources\AlarmRuleResource;
use App\Models\AlarmRule;
use App\Models\Routine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlarmRuleController extends Controller
{
    public function show(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $alarm = $routine->alarmRule;

        return response()->json([
            'alarm_rule' => $alarm ? new AlarmRuleResource($alarm) : null,
        ]);
    }

    public function storeOrUpdate(StoreAlarmRuleRequest $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $alarm = $routine->alarmRule ?: new AlarmRule(['routine_id' => $routine->id]);
        $alarm->fill($request->validated());
        $alarm->routine_id = $routine->id;
        $alarm->save();

        return response()->json([
            'message' => 'Alarm rule saved successfully',
            'alarm_rule' => new AlarmRuleResource($alarm),
        ]);
    }
}
