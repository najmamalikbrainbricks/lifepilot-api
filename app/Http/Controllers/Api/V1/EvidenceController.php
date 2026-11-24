<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EvidenceSubmission;
use App\Models\RoutineStep;
use App\Models\TaskOccurrence;
use App\Services\EvidenceVerificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceController extends Controller
{
    public function __construct(
        protected EvidenceVerificationService $evidenceService
    ) {}

    public function submit(Request $request, TaskOccurrence $occurrence, RoutineStep $step): JsonResponse
    {
        $user = $request->user();

        if ($occurrence->routine->user_id !== $user->id || $step->routine_id !== $occurrence->routine_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Check if request contains a file upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $source = $request->input('source', 'camera_capture');
                $metadata = $request->input('metadata');
                if (is_string($metadata)) {
                    $metadata = json_decode($metadata, true) ?: [];
                }

                $evidence = $this->evidenceService->submitImageEvidence(
                    $user,
                    $occurrence,
                    $step,
                    $file,
                    $source,
                    $metadata
                );

                return response()->json([
                    'message' => 'Image evidence submitted successfully',
                    'evidence' => $evidence,
                    'occurrence' => $occurrence->fresh(['routine.steps', 'stepCompletions.step']),
                ], 201);
            }

            // Otherwise, structured verification (timer, counter, checklist, manual)
            $type = $request->input('evidence_type', 'manual');
            $data = $request->input('data', []);

            $evidence = $this->evidenceService->submitStructuredVerification(
                $user,
                $occurrence,
                $step,
                $type,
                $data
            );

            return response()->json([
                'message' => 'Step verification submitted successfully',
                'evidence' => $evidence,
                'occurrence' => $occurrence->fresh(['routine.steps', 'stepCompletions.step']),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Evidence processing error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, EvidenceSubmission $evidence): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();

        if ($evidence->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            return $this->evidenceService->getEvidenceFile($evidence, $user);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function destroy(Request $request, EvidenceSubmission $evidence): JsonResponse
    {
        $user = $request->user();

        if ($evidence->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->evidenceService->deleteEvidence($evidence, $user);

        return response()->json([
            'message' => 'Evidence deleted successfully',
        ]);
    }

    public function getOccurrenceVerification(Request $request, TaskOccurrence $occurrence): JsonResponse
    {
        $user = $request->user();

        if ($occurrence->routine->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $submissions = $occurrence->evidenceSubmissions()->with('step')->get();

        return response()->json([
            'occurrence_id' => $occurrence->id,
            'evidence_count' => $submissions->count(),
            'submissions' => $submissions,
        ]);
    }
}
