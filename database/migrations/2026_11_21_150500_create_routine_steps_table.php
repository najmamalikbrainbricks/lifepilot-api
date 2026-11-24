<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('estimated_duration')->default(5);
            $table->boolean('required')->default(true);
            $table->string('verification_type', 30)->default('self');
            $table->timestamps();

            $table->index(['routine_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_steps');
    }
};
