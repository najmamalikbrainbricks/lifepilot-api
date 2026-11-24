<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('city')->nullable()->default('Karachi');
            $table->string('country')->nullable()->default('Pakistan');
            $table->decimal('latitude', 10, 7)->default(24.8607);
            $table->decimal('longitude', 10, 7)->default(67.0011);
            $table->string('timezone', 50)->default('Asia/Karachi');
            $table->string('calculation_method', 50)->default('Karachi');
            $table->string('asr_method', 20)->default('Hanafi');
            $table->string('high_latitude_rule', 30)->default('MiddleOfTheNight');
            $table->json('manual_adjustments')->nullable();
            $table->json('enabled_prayers')->nullable();
            $table->json('tahajjud_preference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_settings');
    }
};
