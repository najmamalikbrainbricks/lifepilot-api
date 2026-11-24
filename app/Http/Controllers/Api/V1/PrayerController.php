<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPrayerCompletionRequest;
use App\Http\Requests\StorePrayerOverrideRequest;
use App\Http\Requests\UpdatePrayerSettingRequest;
use App\Models\PrayerCompletion;
use App\Models\PrayerOverride;
use App\Services\PrayerTimeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrayerController extends Controller
{
    public function __construct(
        protected PrayerTimeService $prayerService
    ) {}

    public function getTimes(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam, $tz) : Carbon::now($tz);

        $times = $this->prayerService->getTimesForDate($user, $date);

        return response()->json($times);
    }

    public function getSettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->prayerService->getSettingsForUser($user);

        return response()->json([
            'settings' => $settings,
            'supported_methods' => PrayerTimeService::METHODS,
        ]);
    }

    public function updateSettings(UpdatePrayerSettingRequest $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->prayerService->getSettingsForUser($user);

        $settings->update($request->validated());

        return response()->json([
            'message' => 'Prayer settings updated successfully',
            'settings' => $settings->fresh(),
        ]);
    }

    public function saveOverride(StorePrayerOverrideRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $override = PrayerOverride::updateOrCreate(
            [
                'user_id' => $user->id,
                'date' => $data['date'] ?? null,
            ],
            $data
        );

        return response()->json([
            'message' => 'Mosque timetable override saved successfully',
            'override' => $override,
        ]);
    }

    public function deleteOverride(Request $request, ?int $id = null): JsonResponse
    {
        $user = $request->user();

        $query = PrayerOverride::where('user_id', $user->id);
        if ($id) {
            $query->where('id', $id);
        } else {
            $date = $request->query('date');
            $query->where('date', $date);
        }

        $query->delete();

        return response()->json([
            'message' => 'Override removed successfully',
        ]);
    }

    public function recordCompletion(RecordPrayerCompletionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $completion = PrayerCompletion::updateOrCreate(
            [
                'user_id' => $user->id,
                'prayer_name' => $data['prayer_name'],
                'date' => $data['date'],
            ],
            [
                'status' => $data['status'],
                'completed_at' => $data['completed_at'] ?? Carbon::now(),
                'on_time' => $data['on_time'] ?? true,
                'in_jamaat' => $data['in_jamaat'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Prayer status recorded',
            'completion' => $completion,
        ]);
    }

    public function getHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $month = (int) $request->query('month', Carbon::now($tz)->month);
        $year = (int) $request->query('year', Carbon::now($tz)->year);

        $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $completions = PrayerCompletion::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get();

        $completedCount = $completions->where('status', 'completed')->count();
        $missedCount = $completions->where('status', 'missed')->count();

        return response()->json([
            'month' => $month,
            'year' => $year,
            'total_completed' => $completedCount,
            'total_missed' => $missedCount,
            'completions' => $completions,
        ]);
    }
}
