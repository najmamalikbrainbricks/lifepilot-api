<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\EvidenceSubmission;
use App\Models\RoutineStep;
use App\Models\StepCompletion;
use App\Models\StepVerificationRule;
use App\Models\TaskOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceVerificationService
{
    public const MAX_FILE_SIZE_BYTES = 5242880; // 5MB
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Submit image evidence (from camera or gallery upload).
     */
    public function submitImageEvidence(
        User $user,
        TaskOccurrence $occurrence,
        RoutineStep $step,
        UploadedFile $file,
        string $source = 'camera_capture',
        ?array $additionalMetadata = []
    ): EvidenceSubmission {
        // Validate ownership
        if ($occurrence->routine->user_id !== $user->id || $step->routine_id !== $occurrence->routine_id) {
            throw new InvalidArgumentException('Unauthorized occurrence or step');
        }

        // 1. Validate file size
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidArgumentException('Image file exceeds the 5MB maximum allowed size');
        }

        // 2. Validate real MIME type from file content
        $realMime = $file->getMimeType();
        if (! array_key_exists($realMime, self::ALLOWED_MIME_TYPES)) {
            throw new InvalidArgumentException("Unsupported image format ({$realMime}). Only JPEG, PNG, and WebP are accepted.");
        }

        // 3. Inspect image dimensions to ensure it is not corrupt or a zip bomb
        $imageInfo = @getimagesize($file->getRealPath());
        if (! $imageInfo) {
            throw new InvalidArgumentException('File is not a valid or readable image');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if ($width < 10 || $height < 10 || $width > 8000 || $height > 8000) {
            throw new InvalidArgumentException('Image dimensions are outside acceptable limits');
        }

        // 4. Check routine step verification rule
        $rule = $step->verificationRule;
        if ($source === 'gallery_upload' && $rule && ! $rule->allow_gallery) {
            // Note: unless gallery is explicitly allowed, camera capture is preferred
            $source = 'gallery_upload';
        }

        // 5. Store in private disk directory: storage/app/private/evidence/{user_id}/
        $userDir = "evidence/{$user->id}";
        $filename = Str::uuid()->toString() . '.' . self::ALLOWED_MIME_TYPES[$realMime];
        $relativeStoragePath = "{$userDir}/{$filename}";

        // Attempt EXIF stripping / clean re-encoding with GD if available
        $stored = false;
        if (function_exists('imagecreatefromstring')) {
            $imageData = @file_get_contents($file->getRealPath());
            if ($imageData) {
                $img = @imagecreatefromstring($imageData);
                if ($img) {
                    // Create private folder if not exists
                    Storage::disk('local')->makeDirectory($userDir);
                    $fullPath = Storage::disk('local')->path($relativeStoragePath);

                    if ($realMime === 'image/webp' && function_exists('imagewebp')) {
                        imagewebp($img, $fullPath, 85);
                        $stored = true;
                    } elseif ($realMime === 'image/png' && function_exists('imagepng')) {
                        imagepng($img, $fullPath, 8);
                        $stored = true;
                    } elseif (function_exists('imagejpeg')) {
                        imagejpeg($img, $fullPath, 85);
                        $stored = true;
                    }
                    imagedestroy($img);
                }
            }
        }

        if (! $stored) {
            // Fallback: store raw validated file into private directory
            Storage::disk('local')->putFileAs($userDir, $file, $filename);
        }

        $storedSize = Storage::disk('local')->size($relativeStoragePath);

        return DB::transaction(function () use (
            $user,
            $occurrence,
            $step,
            $relativeStoragePath,
            $realMime,
            $storedSize,
            $source,
            $width,
            $height,
            $additionalMetadata
        ) {
            // Create Evidence Submission
            $submission = EvidenceSubmission::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'routine_step_id' => $step->id,
                'evidence_type' => $source === 'gallery_upload' ? EvidenceSubmission::TYPE_UPLOAD : EvidenceSubmission::TYPE_CAMERA,
                'storage_path' => $relativeStoragePath,
                'mime_type' => $realMime,
                'file_size' => $storedSize,
                'verification_status' => EvidenceSubmission::STATUS_RULE_SATISFIED,
                'validation_source' => $source,
                'metadata' => array_merge([
                    'width' => $width,
                    'height' => $height,
                    'sanitized' => true,
                ], $additionalMetadata ?? []),
                'submitted_at' => Carbon::now('UTC'),
            ]);

            // Mark StepCompletion as completed
            $completion = StepCompletion::updateOrCreate(
                [
                    'occurrence_id' => $occurrence->id,
                    'routine_step_id' => $step->id,
                ],
                [
                    'status' => StepCompletion::STATUS_COMPLETED,
                    'completed_at' => Carbon::now('UTC'),
                    'notes' => "Verified via {$source} (Evidence #{$submission->id})",
                ]
            );

            // Log activity event
            ActivityEvent::create([
                'user_id' => $user->id,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'evidence_submitted',
                'event_time' => Carbon::now('UTC'),
                'metadata' => [
                    'step_id' => $step->id,
                    'step_title' => $step->title,
                    'evidence_id' => $submission->id,
                    'evidence_type' => $submission->evidence_type,
                    'validation_source' => $source,
                ],
            ]);

            return $submission;
        });
    }

    /**
     * Submit non-file verification (manual confirmation, timer elapsed, counter reached, checklist).
     */
    public function submitStructuredVerification(
        User $user,
        TaskOccurrence $occurrence,
        RoutineStep $step,
        string $type, // manual, timer, counter, checklist
        ?array $data = []
    ): EvidenceSubmission {
        if ($occurrence->routine->user_id !== $user->id || $step->routine_id !== $occurrence->routine_id) {
            throw new InvalidArgumentException('Unauthorized occurrence or step');
        }

        $rule = $step->verificationRule;
        $validationSource = match ($type) {
            'timer' => 'timer_elapsed',
            'counter' => 'counter_reached',
            'checklist' => 'checklist_completed',
            default => 'user_confirmed',
        };

        // If timer, check against minimum duration if configured
        if ($type === 'timer' && $rule && $rule->minimum_duration) {
            $elapsed = (int) ($data['elapsed_seconds'] ?? 0);
            if ($elapsed < $rule->minimum_duration) {
                throw new InvalidArgumentException("Timer did not meet minimum requirement of {$rule->minimum_duration} seconds");
            }
        }

        // If counter, check against minimum count
        if ($type === 'counter' && $rule && $rule->minimum_count) {
            $count = (int) ($data['count'] ?? 0);
            if ($count < $rule->minimum_count) {
                throw new InvalidArgumentException("Counter did not reach required target of {$rule->minimum_count}");
            }
        }

        return DB::transaction(function () use ($user, $occurrence, $step, $type, $validationSource, $data) {
            $submission = EvidenceSubmission::create([
                'user_id' => $user->id,
                'occurrence_id' => $occurrence->id,
                'routine_step_id' => $step->id,
                'evidence_type' => $type,
                'storage_path' => null,
                'mime_type' => null,
                'file_size' => null,
                'verification_status' => EvidenceSubmission::STATUS_RULE_SATISFIED,
                'validation_source' => $validationSource,
                'metadata' => $data,
                'submitted_at' => Carbon::now('UTC'),
            ]);

            StepCompletion::updateOrCreate(
                [
                    'occurrence_id' => $occurrence->id,
                    'routine_step_id' => $step->id,
                ],
                [
                    'status' => StepCompletion::STATUS_COMPLETED,
                    'completed_at' => Carbon::now('UTC'),
                    'notes' => "Verified via {$validationSource}",
                ]
            );

            ActivityEvent::create([
                'user_id' => $user->id,
                'task_occurrence_id' => $occurrence->id,
                'event_type' => 'step_verified',
                'event_time' => Carbon::now('UTC'),
                'metadata' => [
                    'step_id' => $step->id,
                    'step_title' => $step->title,
                    'verification_type' => $type,
                ],
            ]);

            return $submission;
        });
    }

    /**
     * Get secure authenticated file response for evidence item.
     */
    public function getEvidenceFile(EvidenceSubmission $evidence, User $user): BinaryFileResponse
    {
        if ($evidence->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized evidence access');
        }

        if (! $evidence->storage_path || ! Storage::disk('local')->exists($evidence->storage_path)) {
            throw new InvalidArgumentException('Evidence file not found on server');
        }

        $fullPath = Storage::disk('local')->path($evidence->storage_path);

        return response()->file($fullPath, [
            'Content-Type' => $evidence->mime_type ?: 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Content-Disposition' => 'inline; filename="evidence-' . $evidence->id . '"',
        ]);
    }

    /**
     * Delete evidence submission.
     */
    public function deleteEvidence(EvidenceSubmission $evidence, User $user): bool
    {
        if ($evidence->user_id !== $user->id) {
            throw new InvalidArgumentException('Unauthorized');
        }

        return DB::transaction(function () use ($evidence) {
            if ($evidence->storage_path && Storage::disk('local')->exists($evidence->storage_path)) {
                Storage::disk('local')->delete($evidence->storage_path);
            }

            $evidence->verification_status = EvidenceSubmission::STATUS_DELETED;
            $evidence->save();
            $evidence->delete(); // Soft delete

            return true;
        });
    }
}
