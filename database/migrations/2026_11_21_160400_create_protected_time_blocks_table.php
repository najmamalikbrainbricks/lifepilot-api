<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protected_time_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 50)->default('Focus');
            $table->string('start_time', 10);
            $table->string('end_time', 10);
            $table->json('days_of_week')->nullable();
            $table->integer('minimum_duration_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_time_blocks');
    }
};
