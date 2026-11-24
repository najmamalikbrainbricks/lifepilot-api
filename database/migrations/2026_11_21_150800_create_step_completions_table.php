<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('task_occurrences')->cascadeOnDelete();
            $table->foreignId('routine_step_id')->constrained('routine_steps')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['occurrence_id', 'routine_step_id']);
            $table->index(['occurrence_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_completions');
    }
};
