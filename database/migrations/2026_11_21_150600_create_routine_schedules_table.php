<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('recurrence_type', 30)->default('daily');
            $table->json('selected_weekdays')->nullable();
            $table->string('local_start_time', 10);
            $table->string('timezone', 50)->default('UTC');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('reminder_offset')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['routine_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_schedules');
    }
};
