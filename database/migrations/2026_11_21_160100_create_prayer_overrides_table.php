<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->string('fajr', 10)->nullable();
            $table->string('dhuhr', 10)->nullable();
            $table->string('asr', 10)->nullable();
            $table->string('maghrib', 10)->nullable();
            $table->string('isha', 10)->nullable();
            $table->string('note')->nullable()->default('Mosque timetable override');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_overrides');
    }
};
