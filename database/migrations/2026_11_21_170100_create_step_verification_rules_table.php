<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_verification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_step_id')->unique()->constrained('routine_steps')->cascadeOnDelete();
            $table->string('verification_type', 30)->default('self'); // self, camera, upload, checklist, timer, counter, multi
            $table->boolean('required')->default(true);
            $table->integer('minimum_duration')->nullable(); // in seconds for timer
            $table->integer('minimum_count')->nullable(); // for counter
            $table->boolean('allow_gallery')->default(false); // whether gallery upload is acceptable for camera steps
            $table->string('fallback_type', 30)->default('self'); // fallback if camera permission denied
            $table->json('rule_configuration')->nullable(); // checklist items, audio triggers, combined rules
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_verification_rules');
    }
};
