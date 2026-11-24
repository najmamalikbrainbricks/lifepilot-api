<?php

use App\Http\Controllers\Api\V1\AccountabilityController;
use App\Http\Controllers\Api\V1\AlarmRuleController;
use App\Http\Controllers\Api\V1\AlarmWorkflowController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DailyReportController;
use App\Http\Controllers\Api\V1\DataMigrationController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\PlannerController;
use App\Http\Controllers\Api\V1\PrayerController;
use App\Http\Controllers\Api\V1\ProtectedTimeBlockController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\RoutineScheduleController;
use App\Http\Controllers\Api\V1\RoutineStepController;
use App\Http\Controllers\Api\V1\TaskOccurrenceController;
use App\Http\Controllers\Api\V1\TimerSessionController;
use App\Http\Controllers\Api\V1\UserPreferenceController;
use App\Http\Controllers\Api\V1\WorkShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {

        // Current User & Session
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Profile & Preferences
        Route::get('/preferences', [UserPreferenceController::class, 'show']);
        Route::put('/preferences', [UserPreferenceController::class, 'update']);

        // Goals
        Route::apiResource('goals', GoalController::class)->except(['show']);

        // Work Shifts
        Route::apiResource('work-shifts', WorkShiftController::class)->except(['show']);

        // Protected Focus Blocks
        Route::apiResource('protected-blocks', ProtectedTimeBlockController::class)->except(['show']);

        // Routines & Builder
        Route::apiResource('routines', RoutineController::class);
        Route::post('/routines/{routine}/archive', [RoutineController::class, 'archive']);
        Route::post('/routines/{routine}/unarchive', [RoutineController::class, 'unarchive']);

        // Routine Steps
        Route::get('/routines/{routine}/steps', [RoutineStepController::class, 'index']);
        Route::post('/routines/{routine}/steps', [RoutineStepController::class, 'store']);
        Route::post('/routines/{routine}/steps/reorder', [RoutineStepController::class, 'reorder']);
        Route::put('/routines/{routine}/steps/{step}', [RoutineStepController::class, 'update']);
        Route::delete('/routines/{routine}/steps/{step}', [RoutineStepController::class, 'destroy']);

        // Routine Schedules
        Route::get('/routines/{routine}/schedules', [RoutineScheduleController::class, 'index']);
        Route::post('/routines/{routine}/schedules', [RoutineScheduleController::class, 'store']);
        Route::put('/routines/{routine}/schedules/{schedule}', [RoutineScheduleController::class, 'update']);
        Route::delete('/routines/{routine}/schedules/{schedule}', [RoutineScheduleController::class, 'destroy']);

        // Routine Alarm Rules
        Route::get('/routines/{routine}/alarm-rule', [AlarmRuleController::class, 'show']);
        Route::post('/routines/{routine}/alarm-rule', [AlarmRuleController::class, 'storeOrUpdate']);

        // Task Occurrences & Execution Engine
        Route::get('/occurrences', [TaskOccurrenceController::class, 'index']);
        Route::get('/occurrences/{occurrence}', [TaskOccurrenceController::class, 'show']);
        Route::post('/occurrences/{occurrence}/start', [TaskOccurrenceController::class, 'start']);
        Route::post('/occurrences/{occurrence}/steps/{step}/complete', [TaskOccurrenceController::class, 'completeStep']);
        Route::post('/occurrences/{occurrence}/steps/{step}/skip', [TaskOccurrenceController::class, 'skipStep']);
        Route::post('/occurrences/{occurrence}/complete', [TaskOccurrenceController::class, 'complete']);
        Route::post('/occurrences/{occurrence}/skip', [TaskOccurrenceController::class, 'skip']);
        Route::post('/occurrences/{occurrence}/pause', [TaskOccurrenceController::class, 'pause']);

        // Task Rescheduling
        Route::post('/occurrences/{occurrence}/reschedule', [PlannerController::class, 'rescheduleOccurrence']);
        Route::post('/occurrences/{occurrence}/reset-reschedule', [PlannerController::class, 'resetOccurrenceReschedule']);

        // Smart Daily Planner
        Route::get('/planner/daily', [PlannerController::class, 'daily']);
        Route::get('/planner/weekly', [PlannerController::class, 'weekly']);
        Route::get('/planner/monthly', [PlannerController::class, 'monthly']);
        Route::get('/planner/conflicts', [PlannerController::class, 'conflicts']);
        Route::post('/planner/suggestions', [PlannerController::class, 'suggestions']);

        // Islamic Prayer Times
        Route::get('/prayers/times', [PrayerController::class, 'getTimes']);
        Route::get('/prayers/settings', [PrayerController::class, 'getSettings']);
        Route::put('/prayers/settings', [PrayerController::class, 'updateSettings']);
        Route::post('/prayers/overrides', [PrayerController::class, 'saveOverride']);
        Route::delete('/prayers/overrides', [PrayerController::class, 'deleteOverride']);
        Route::delete('/prayers/overrides/{id}', [PrayerController::class, 'deleteOverride']);
        Route::post('/prayers/completions', [PrayerController::class, 'recordCompletion']);
        Route::get('/prayers/history', [PrayerController::class, 'getHistory']);

        // Reports & Dashboard
        Route::get('/reports/daily', [DailyReportController::class, 'show']);

        // Browser Data Migration
        Route::post('/migration/import', [DataMigrationController::class, 'import']);

        // Smart Alarm Workflow Engine (Phase 05)
        Route::get('/alarms/due', [AlarmWorkflowController::class, 'due']);
        Route::post('/alarms/{occurrence}/trigger', [AlarmWorkflowController::class, 'trigger']);
        Route::post('/alarms/{occurrence}/acknowledge', [AlarmWorkflowController::class, 'acknowledge']);
        Route::post('/alarms/{occurrence}/snooze', [AlarmWorkflowController::class, 'snooze']);
        Route::post('/alarms/{occurrence}/dismiss', [AlarmWorkflowController::class, 'dismiss']);
        Route::post('/alarms/emergency-stop', [AlarmWorkflowController::class, 'emergencyStop']);

        // Proof Verification & Evidence Engine (Phase 05)
        Route::get('/occurrences/{occurrence}/verification', [EvidenceController::class, 'getOccurrenceVerification']);
        Route::post('/occurrences/{occurrence}/steps/{step}/evidence', [EvidenceController::class, 'submit']);
        Route::get('/evidence/{evidence}', [EvidenceController::class, 'show']);
        Route::delete('/evidence/{evidence}', [EvidenceController::class, 'destroy']);

        // Timer Sessions Engine (Phase 05)
        Route::post('/timer-sessions', [TimerSessionController::class, 'store']);
        Route::post('/timer-sessions/{timerSession}/pause', [TimerSessionController::class, 'pause']);
        Route::post('/timer-sessions/{timerSession}/resume', [TimerSessionController::class, 'resume']);
        Route::post('/timer-sessions/{timerSession}/complete', [TimerSessionController::class, 'complete']);

        // Accountability & Verification Reports (Phase 05)
        Route::get('/accountability/history', [AccountabilityController::class, 'history']);
        Route::get('/accountability/summary', [AccountabilityController::class, 'summary']);
    });
});
