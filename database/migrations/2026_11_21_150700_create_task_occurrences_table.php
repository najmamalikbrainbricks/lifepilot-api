<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('routine_schedules')->nullOnDelete();
            $table->timestamp('due_at')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 30)->default('scheduled')->index();
            $table->integer('actual_duration')->nullable(); // stored in seconds
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['routine_id', 'schedule_id', 'due_at'], 'routine_sched_due_unique');
            $table->index(['routine_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_occurrences');
    }
};
