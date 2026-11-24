<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme', 10)->default('dark');
            $table->string('time_format', 10)->default('12h');
            $table->json('reminder_preferences')->nullable();
            $table->decimal('sleep_goal', 4, 1)->default(8.0);
            $table->string('bedtime', 10)->default('01:30');
            $table->string('wake_time', 10)->default('09:30');
            $table->integer('focus_goal')->default(60);
            $table->string('focus_category')->default('Agency');
            $table->json('activity_preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
