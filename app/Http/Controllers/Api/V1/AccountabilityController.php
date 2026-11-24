<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AccountabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountabilityController extends Controller
{
    public function __construct(
        protected AccountabilityService $accountabilityService
    ) {}

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 50);

        $history = $this->accountabilityService->getAccountabilityHistory($user, $limit);

        return response()->json([
            'count' => $history->count(),
            'history' => $history,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $user->timezone ?: 'Asia/Karachi';

        $startParam = $request->query('start_date');
        $endParam = $request->query('end_date');

        $start = $startParam ? Carbon::parse($startParam, $tz)->startOfDay() : null;
        $end = $endParam ? Carbon::parse($endParam, $tz)->endOfDay() : null;

        $summary = $this->accountabilityService->getAccountabilitySummary($user, $start, $end);

        return response()->json($summary);
    }
}
