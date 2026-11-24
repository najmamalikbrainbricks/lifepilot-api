<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferenceRequest;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $pref = $user->preference ?: UserPreference::create([
            'user_id' => $user->id,
            'theme' => 'dark',
            'time_format' => '12h',
            'reminder_preferences' => [],
            'sleep_goal' => 8.0,
            'bedtime' => '01:30',
            'wake_time' => '09:30',
            'focus_goal' => 60,
            'focus_category' => 'Agency',
            'activity_preferences' => [],
        ]);

        return response()->json([
            'preference' => new UserPreferenceResource($pref),
        ]);
    }

    public function update(UpdatePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $pref = $user->preference ?: new UserPreference(['user_id' => $user->id]);

        $pref->fill($request->validated());
        $pref->user_id = $user->id;
        $pref->save();

        return response()->json([
            'message' => 'Preferences updated successfully',
            'preference' => new UserPreferenceResource($pref),
        ]);
    }
}
