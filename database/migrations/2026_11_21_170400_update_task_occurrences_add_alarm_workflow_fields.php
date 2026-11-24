<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_occurrences', function (Blueprint $table) {
            $table->timestamp('snoozed_until')->nullable()->after('rescheduled_at')->index();
            $table->integer('alarm_attempts')->default(0)->after('snoozed_until');
            $table->timestamp('ringing_started_at')->nullable()->after('alarm_attempts');
            $table->timestamp('acknowledged_at')->nullable()->after('ringing_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('task_occurrences', function (Blueprint $table) {
            $table->dropColumn(['snoozed_until', 'alarm_attempts', 'ringing_started_at', 'acknowledged_at']);
        });
    }
};
