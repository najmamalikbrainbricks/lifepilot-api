<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RescheduleOccurrenceRequest;
use App\Http\Requests\SuggestSlotRequest;
use App\Http\Resources\TaskOccurrenceResource;
use App\Models\TaskOccurrence;
use App\Services\IntelligentSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlannerController extends Controller
{
    public function __construct(
        protected IntelligentSchedulingService $schedulingService
    ) {}

    public function daily(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam, $tz) : Carbon::now($tz);

        $timeline = $this->schedulingService->getDailyTimeline($user, $date);

        return response()->json($timeline);
    }

    public function weekly(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam, $tz) : Carbon::now($tz);

        $calendar = $this->schedulingService->getWeeklyCalendar($user, $date);

        return response()->json($calendar);
    }

    public function monthly(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $month = (int) $request->query('month', Carbon::now($tz)->month);
        $year = (int) $request->query('year', Carbon::now($tz)->year);

        $calendar = $this->schedulingService->getMonthlyCalendar($user, $year, $month);

        return response()->json($calendar);
    }

    public function conflicts(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam, $tz) : Carbon::now($tz);

        $timeline = $this->schedulingService->getDailyTimeline($user, $date);

        return response()->json([
            'date' => $timeline['date'],
            'timezone' => $timeline['timezone'],
            'conflicts_count' => count($timeline['conflicts']),
            'conflicts' => $timeline['conflicts'],
        ]);
    }

    public function suggestions(SuggestSlotRequest $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $duration = (int) $request->validated('duration_minutes');
        $dateParam = $request->validated('date');
        $date = $dateParam ? Carbon::parse($dateParam, $tz) : Carbon::now($tz);

        $suggestions = $this->schedulingService->suggestAvailableSlots($user, $duration, $date);

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'required_duration_minutes' => $duration,
            'suggestions_count' => count($suggestions),
            'suggestions' => $suggestions,
        ]);
    }

    public function rescheduleOccurrence(RescheduleOccurrenceRequest $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();

        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $newDueAt = Carbon::parse($request->validated('new_due_at'));
        $reason = $request->validated('reason');

        $updated = $this->schedulingService->rescheduleOccurrence($occurrence, $newDueAt, $reason);

        return response()->json([
            'message' => 'Task occurrence rescheduled successfully',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }

    public function resetOccurrenceReschedule(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();

        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updated = $this->schedulingService->resetReschedule($occurrence);

        return response()->json([
            'message' => 'Task occurrence reset to original schedule',
            'occurrence' => new TaskOccurrenceResource($updated),
        ]);
    }
}
