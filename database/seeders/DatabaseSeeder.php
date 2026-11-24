<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Routine;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WorkShift;
use App\Services\RoutineSchedulerService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'alex@lifepilot.io'],
            [
                'name' => 'Alex Morgan',
                'password' => Hash::make('password123'),
                'timezone' => 'Asia/Karachi',
                'locale' => 'en',
                'status' => 'active',
            ]
        );

        // Preferences
        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => 'dark',
                'time_format' => '12h',
                'reminder_preferences' => [
                    'interval_minutes' => 5,
                    'strict_mode' => false,
                    'prayers_enabled' => true,
                    'wudu_reminders' => true,
                    'tahajjud_enabled' => true,
                ],
                'sleep_goal' => 8.0,
                'bedtime' => '01:30',
                'wake_time' => '09:30',
                'focus_goal' => 60,
                'focus_category' => 'Agency',
                'activity_preferences' => [
                    'step_goal' => 6000,
                    'workout_days' => 3,
                ],
            ]
        );

        // Work shift
        WorkShift::firstOrCreate(
            ['user_id' => $user->id, 'job_name' => 'Product Designer & Mentor'],
            [
                'start_time' => '19:00',
                'end_time' => '01:00',
                'days_of_week' => [1, 2, 3, 4, 5],
                'fixed_or_flexible' => 'fixed',
                'overnight' => true,
                'enabled' => true,
            ]
        );

        // Goals
        $goals = ['Five daily prayers', 'Agency growth', 'Better sleep', 'Morning discipline'];
        foreach ($goals as $index => $goalTitle) {
            Goal::firstOrCreate(
                ['user_id' => $user->id, 'title' => $goalTitle],
                ['priority' => $index + 1, 'active' => true]
            );
        }

        // Routine 1: Morning Prayer & Intention
        $routine1 = Routine::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Morning Prayer & Intention'],
            [
                'description' => 'Gentle waking, mindful wudu, and Fajr prayer to begin the day with gratitude.',
                'category' => 'Spiritual',
                'priority' => 'high',
                'estimated_duration' => 25,
                'icon' => 'Waves',
                'color' => '#38bdf8',
                'enabled' => true,
            ]
        );

        if ($routine1->steps()->count() === 0) {
            $routine1->steps()->createMany([
                ['title' => 'Wake up & recite morning remembrance', 'description' => 'Acknowledge the gift of a new day', 'sort_order' => 0, 'estimated_duration' => 5, 'required' => true, 'verification_type' => 'self'],
                ['title' => 'Mindful Wudu with flowing water', 'description' => 'Wash with presence and intention', 'sort_order' => 1, 'estimated_duration' => 5, 'required' => true, 'verification_type' => 'self'],
                ['title' => 'Fajr prayer', 'description' => 'Perform 2 Sunnah and 2 Fard rakat', 'sort_order' => 2, 'estimated_duration' => 10, 'required' => true, 'verification_type' => 'self'],
                ['title' => 'Morning contemplation & intention', 'description' => 'Sit quietly for 5 minutes and set intentions for today', 'sort_order' => 3, 'estimated_duration' => 5, 'required' => false, 'verification_type' => 'self'],
            ]);
        }

        if ($routine1->schedules()->count() === 0) {
            $routine1->schedules()->create([
                'recurrence_type' => 'daily',
                'local_start_time' => '05:30',
                'timezone' => 'Asia/Karachi',
                'enabled' => true,
            ]);
        }

        if (! $routine1->alarmRule) {
            $routine1->alarmRule()->create([
                'enabled' => true,
                'sound' => 'flowing_water',
                'repeat_interval' => 5,
                'max_attempts' => 3,
                'snooze_duration' => 5,
                'verification_required' => false,
                'escalation_preference' => 'gentle',
            ]);
        }

        // Routine 2: Creative Deep Work
        $routine2 = Routine::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Creative Deep Work'],
            [
                'description' => 'High-leverage agency and product work in single-task focus.',
                'category' => 'Work',
                'priority' => 'high',
                'estimated_duration' => 90,
                'icon' => 'Target',
                'color' => '#6366f1',
                'enabled' => true,
            ]
        );

        if ($routine2->steps()->count() === 0) {
            $routine2->steps()->createMany([
                ['title' => 'Clear workspace & define one primary task', 'description' => 'Mute notifications and open only required tools', 'sort_order' => 0, 'estimated_duration' => 5, 'required' => true, 'verification_type' => 'checklist'],
                ['title' => 'Sprint 1: Deep focus execution', 'description' => 'Dedicated progress without task-switching', 'sort_order' => 1, 'estimated_duration' => 45, 'required' => true, 'verification_type' => 'timer'],
                ['title' => 'Refuel & eye break', 'description' => 'Step away from screen, hydrate', 'sort_order' => 2, 'estimated_duration' => 5, 'required' => false, 'verification_type' => 'self'],
                ['title' => 'Sprint 2: Wrap up & review', 'description' => 'Document findings, commit work', 'sort_order' => 3, 'estimated_duration' => 35, 'required' => true, 'verification_type' => 'checklist'],
            ]);
        }

        if ($routine2->schedules()->count() === 0) {
            $routine2->schedules()->create([
                'recurrence_type' => 'daily',
                'local_start_time' => '09:00',
                'timezone' => 'Asia/Karachi',
                'enabled' => true,
            ]);
        }

        // Protected Focus Block
        \App\Models\ProtectedTimeBlock::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'Agency Creative Deep Work'],
            [
                'category' => 'Agency Focus',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'days_of_week' => [1, 2, 3, 4, 5],
                'minimum_duration_minutes' => 60,
                'enabled' => true,
            ]
        );

        // Prayer Settings
        \App\Models\PrayerSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'timezone' => 'Asia/Karachi',
                'calculation_method' => 'Karachi',
                'asr_method' => 'Hanafi',
                'manual_adjustments' => ['fajr' => 0, 'dhuhr' => 0, 'asr' => 0, 'maghrib' => 0, 'isha' => 0],
                'enabled_prayers' => ['fajr' => true, 'dhuhr' => true, 'asr' => true, 'maghrib' => true, 'isha' => true, 'tahajjud' => true],
                'tahajjud_preference' => ['enabled' => true, 'duration_minutes' => 30, 'preparation_minutes' => 10, 'offset_before_fajr' => 45],
            ]
        );

        // Generate occurrences for today
        $scheduler = app(RoutineSchedulerService::class);
        $today = Carbon::now('Asia/Karachi')->startOfDay();
        $scheduler->generateForUser($user, $today, $today);
    }
}
