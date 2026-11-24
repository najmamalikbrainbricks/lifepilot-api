<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoutineRequest;
use App\Http\Requests\UpdateRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\AlarmRule;
use App\Models\Routine;
use App\Models\RoutineSchedule;
use App\Models\RoutineStep;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoutineController extends Controller
{
    public function __construct(
        protected RoutineSchedulerService $schedulerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->routines()->with(['steps', 'schedules', 'alarmRule']);

        // Filter by status
        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'paused') {
            $query->paused();
        } elseif ($status === 'archived') {
            $query->archived();
        }

        // Filter by category
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // Filter by search query
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $routines = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'routines' => RoutineResource::collection($routines),
            'meta' => [
                'total' => $routines->count(),
                'active_count' => $user->routines()->active()->count(),
                'paused_count' => $user->routines()->paused()->count(),
                'archived_count' => $user->routines()->archived()->count(),
            ],
        ]);
    }

    public function store(StoreRoutineRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $routine = DB::transaction(function () use ($user, $validated) {
            $routine = $user->routines()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'],
                'priority' => $validated['priority'] ?? 'medium',
                'estimated_duration' => $validated['estimated_duration'] ?? 15,
                'icon' => $validated['icon'] ?? null,
                'color' => $validated['color'] ?? null,
                'enabled' => $validated['enabled'] ?? true,
            ]);

            // Create steps if passed
            if (! empty($validated['steps'])) {
                foreach ($validated['steps'] as $index => $stepData) {
                    $routine->steps()->create([
                        'title' => $stepData['title'],
                        'description' => $stepData['description'] ?? null,
                        'sort_order' => $stepData['sort_order'] ?? $index,
                        'estimated_duration' => $stepData['estimated_duration'] ?? 5,
                        'required' => $stepData['required'] ?? true,
                        'verification_type' => $stepData['verification_type'] ?? 'self',
                    ]);
                }
            }

            // Create schedule if passed
            if (! empty($validated['schedule'])) {
                $schedData = $validated['schedule'];
                $routine->schedules()->create([
                    'recurrence_type' => $schedData['recurrence_type'],
                    'selected_weekdays' => $schedData['selected_weekdays'] ?? null,
                    'local_start_time' => $schedData['local_start_time'],
                    'timezone' => $schedData['timezone'] ?? $user->timezone ?? 'UTC',
                    'start_date' => $schedData['start_date'] ?? null,
                    'end_date' => $schedData['end_date'] ?? null,
                    'reminder_offset' => $schedData['reminder_offset'] ?? 0,
                    'enabled' => $schedData['enabled'] ?? true,
                ]);
            }

            // Create alarm rule if passed
            if (! empty($validated['alarm_rule'])) {
                $alarmData = $validated['alarm_rule'];
                $routine->alarmRule()->create([
                    'enabled' => $alarmData['enabled'] ?? true,
                    'sound' => $alarmData['sound'] ?? 'flowing_water',
                    'repeat_interval' => $alarmData['repeat_interval'] ?? 5,
                    'max_attempts' => $alarmData['max_attempts'] ?? 3,
                    'snooze_duration' => $alarmData['snooze_duration'] ?? 5,
                    'verification_required' => $alarmData['verification_required'] ?? false,
                    'escalation_preference' => $alarmData['escalation_preference'] ?? 'gentle',
                ]);
            }

            return $routine;
        });

        // Trigger scheduler for today if schedule is active
        $userTz = $user->timezone ?: 'UTC';
        $today = Carbon::now($userTz)->startOfDay();
        $this->schedulerService->generateOccurrencesForRoutine($routine, $today, $today);

        return response()->json([
            'message' => 'Routine created successfully',
            'routine' => new RoutineResource($routine->load(['steps', 'schedules', 'alarmRule'])),
        ], 201);
    }

    public function show(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'routine' => new RoutineResource($routine->load(['steps', 'schedules', 'alarmRule', 'occurrences'])),
        ]);
    }

    public function update(UpdateRoutineRequest $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($routine, $user, $validated) {
            $routine->fill(array_filter([
                'title' => $validated['title'] ?? null,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
                'category' => $validated['category'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'icon' => $validated['icon'] ?? null,
                'color' => $validated['color'] ?? null,
                'enabled' => array_key_exists('enabled', $validated) ? $validated['enabled'] : null,
            ], fn ($v) => $v !== null));

            if (isset($validated['archived'])) {
                $routine->archived_at = $validated['archived'] ? Carbon::now() : null;
            }

            $routine->save();

            // Steps synchronization if provided
            if (isset($validated['steps'])) {
                // Delete existing steps and re-create to keep ordering clean
                $routine->steps()->delete();
                foreach ($validated['steps'] as $index => $stepData) {
                    $routine->steps()->create([
                        'title' => $stepData['title'],
                        'description' => $stepData['description'] ?? null,
                        'sort_order' => $stepData['sort_order'] ?? $index,
                        'estimated_duration' => $stepData['estimated_duration'] ?? 5,
                        'required' => $stepData['required'] ?? true,
                        'verification_type' => $stepData['verification_type'] ?? 'self',
                    ]);
                }
            }

            // Schedule update if provided
            if (isset($validated['schedule'])) {
                $schedData = $validated['schedule'];
                $schedule = $routine->schedules()->first();
                if ($schedule) {
                    $schedule->update([
                        'recurrence_type' => $schedData['recurrence_type'],
                        'selected_weekdays' => $schedData['selected_weekdays'] ?? null,
                        'local_start_time' => $schedData['local_start_time'],
                        'timezone' => $schedData['timezone'] ?? $schedule->timezone,
                        'start_date' => $schedData['start_date'] ?? null,
                        'end_date' => $schedData['end_date'] ?? null,
                        'reminder_offset' => $schedData['reminder_offset'] ?? 0,
                        'enabled' => $schedData['enabled'] ?? true,
                    ]);
                } else {
                    $routine->schedules()->create([
                        'recurrence_type' => $schedData['recurrence_type'],
                        'selected_weekdays' => $schedData['selected_weekdays'] ?? null,
                        'local_start_time' => $schedData['local_start_time'],
                        'timezone' => $schedData['timezone'] ?? $user->timezone ?? 'UTC',
                        'start_date' => $schedData['start_date'] ?? null,
                        'end_date' => $schedData['end_date'] ?? null,
                        'reminder_offset' => $schedData['reminder_offset'] ?? 0,
                        'enabled' => $schedData['enabled'] ?? true,
                    ]);
                }
            }

            // Alarm rule update if provided
            if (isset($validated['alarm_rule'])) {
                $alarmData = $validated['alarm_rule'];
                $alarmRule = $routine->alarmRule ?: new AlarmRule(['routine_id' => $routine->id]);
                $alarmRule->fill([
                    'enabled' => $alarmData['enabled'] ?? true,
                    'sound' => $alarmData['sound'] ?? 'flowing_water',
                    'repeat_interval' => $alarmData['repeat_interval'] ?? 5,
                    'max_attempts' => $alarmData['max_attempts'] ?? 3,
                    'snooze_duration' => $alarmData['snooze_duration'] ?? 5,
                    'verification_required' => $alarmData['verification_required'] ?? false,
                    'escalation_preference' => $alarmData['escalation_preference'] ?? 'gentle',
                ]);
                $alarmRule->save();
            }
        });

        return response()->json([
            'message' => 'Routine updated successfully',
            'routine' => new RoutineResource($routine->fresh(['steps', 'schedules', 'alarmRule'])),
        ]);
    }

    public function destroy(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $routine->delete();

        return response()->json([
            'message' => 'Routine deleted successfully',
        ]);
    }

    public function archive(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $routine->archived_at = Carbon::now();
        $routine->save();

        return response()->json([
            'message' => 'Routine archived successfully',
            'routine' => new RoutineResource($routine->fresh(['steps', 'schedules', 'alarmRule'])),
        ]);
    }

    public function unarchive(Request $request, Routine $routine): JsonResponse
    {
        if ($routine->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $routine->archived_at = null;
        $routine->save();

        return response()->json([
            'message' => 'Routine unarchived successfully',
            'routine' => new RoutineResource($routine->fresh(['steps', 'schedules', 'alarmRule'])),
        ]);
    }
}
