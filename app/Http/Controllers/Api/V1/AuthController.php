<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'timezone' => $data['timezone'] ?? 'Asia/Karachi',
            'locale' => $data['locale'] ?? 'en',
            'status' => 'active',
        ]);

        // Create default preferences
        UserPreference::create([
            'user_id' => $user->id,
            'theme' => 'dark',
            'time_format' => '12h',
            'reminder_preferences' => [
                'interval_minutes' => 5,
                'strict_mode' => false,
                'prayers_enabled' => true,
                'wudu_reminders' => true,
            ],
            'sleep_goal' => 8.0,
            'bedtime' => '01:30',
            'wake_time' => '09:30',
            'focus_goal' => 60,
            'focus_category' => 'Agency',
            'activity_preferences' => [
                'step_goal' => 6000,
                'workout_days' => 3,
            ],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'token' => $token,
            'user' => new UserResource($user->load(['preference', 'goals', 'workShifts'])),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $deviceName = $request->device_name ?: 'browser';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'token' => $token,
            'user' => new UserResource($user->load(['preference', 'goals', 'workShifts'])),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => new UserResource($user->load(['preference', 'goals', 'workShifts'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
