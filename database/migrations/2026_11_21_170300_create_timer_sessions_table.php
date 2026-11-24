<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occurrence_id')->constrained('task_occurrences')->cascadeOnDelete();
            $table->foreignId('routine_step_id')->nullable()->constrained('routine_steps')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('elapsed_seconds')->default(0);
            $table->string('status', 30)->default('running'); // running, paused, completed, cancelled
            $table->integer('target_duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['occurrence_id', 'routine_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timer_sessions');
    }
};
