<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prayer_name', 20);
            $table->date('date');
            $table->string('status', 20)->default('completed'); // completed, missed, excused
            $table->timestamp('completed_at')->nullable();
            $table->boolean('on_time')->default(true);
            $table->boolean('in_jamaat')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'prayer_name', 'date']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_completions');
    }
};
