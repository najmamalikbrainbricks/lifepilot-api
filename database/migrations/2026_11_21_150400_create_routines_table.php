<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('Personal');
            $table->string('priority', 20)->default('medium');
            $table->integer('estimated_duration')->default(15);
            $table->string('icon', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'enabled', 'archived_at']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routines');
    }
};
