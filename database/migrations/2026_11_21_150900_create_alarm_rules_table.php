<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarm_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('sound', 50)->default('flowing_water');
            $table->integer('repeat_interval')->default(5);
            $table->integer('max_attempts')->default(3);
            $table->integer('snooze_duration')->default(5);
            $table->boolean('verification_required')->default(false);
            $table->string('escalation_preference', 50)->default('gentle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarm_rules');
    }
};
