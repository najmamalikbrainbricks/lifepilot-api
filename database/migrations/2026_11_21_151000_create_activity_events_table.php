<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_occurrence_id')->nullable()->constrained('task_occurrences')->nullOnDelete();
            $table->string('event_type', 50);
            $table->timestamp('event_time')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
