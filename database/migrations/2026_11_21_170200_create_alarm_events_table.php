<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occurrence_id')->constrained('task_occurrences')->cascadeOnDelete();
            $table->foreignId('alarm_rule_id')->nullable()->constrained('alarm_rules')->nullOnDelete();
            $table->string('event_type', 50); // due, ringing, acknowledged, snoozed, dismissed, auto_missed, emergency_stop
            $table->timestamp('event_time')->index();
            $table->json('metadata')->nullable(); // attempt, snooze_minutes, ring_seconds, reason
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index(['occurrence_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_events');
    }
};
