<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occurrence_id')->constrained('task_occurrences')->cascadeOnDelete();
            $table->foreignId('routine_step_id')->constrained('routine_steps')->cascadeOnDelete();
            $table->string('evidence_type', 30)->default('camera'); // camera, upload, manual, checklist, timer, counter
            $table->string('storage_path')->nullable(); // Relative path on private disk (null for non-file proofs)
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // In bytes
            $table->string('verification_status', 30)->default('rule_satisfied'); // pending, submitted, rule_satisfied, self_reported, rejected, deleted, overridden
            $table->string('validation_source', 50)->default('camera_capture'); // user_confirmed, camera_capture, gallery_upload, timer_elapsed, counter_reached, manual_override
            $table->json('metadata')->nullable(); // e.g. dimensions, hash, timer elapsed, counter value
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'verification_status']);
            $table->index(['occurrence_id', 'routine_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_submissions');
    }
};
