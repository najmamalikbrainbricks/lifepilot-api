<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_occurrences', function (Blueprint $table) {
            $table->timestamp('original_due_at')->nullable()->after('due_at');
            $table->timestamp('rescheduled_at')->nullable()->after('completed_at');
            $table->string('reschedule_reason')->nullable()->after('rescheduled_at');
            $table->boolean('is_rescheduled')->default(false)->after('reschedule_reason');
        });
    }

    public function down(): void
    {
        Schema::table('task_occurrences', function (Blueprint $table) {
            $table->dropColumn(['original_due_at', 'rescheduled_at', 'reschedule_reason', 'is_rescheduled']);
        });
    }
};
