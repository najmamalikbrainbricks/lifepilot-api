<?php

namespace Tests\Feature;

use App\Models\PrayerOverride;
use App\Models\PrayerSetting;
use App\Models\User;
use App\Services\PrayerTimeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_prayer_times_for_user_location(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        $response = $this->actingAs($user)->getJson('/api/v1/prayers/times?date=2026-11-21');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'timezone',
                'location' => ['city', 'country', 'latitude', 'longitude'],
                'method' => ['key', 'name', 'asr'],
                'calculated_times' => ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'],
                'effective_times' => ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'],
                'tahajjud' => ['enabled', 'last_third_start', 'midnight', 'suggested_start'],
            ]);

        $times = $response->json('effective_times');
        $this->assertNotEmpty($times['fajr']);
        $this->assertNotEmpty($times['dhuhr']);
        $this->assertNotEmpty($times['asr']);
        $this->assertNotEmpty($times['maghrib']);
        $this->assertNotEmpty($times['isha']);
    }

    public function test_different_calculation_methods_and_asr_schools_produce_different_times(): void
    {
        $service = app(PrayerTimeService::class);

        // Karachi method with Hanafi Asr
        $karachiTimes = $service->calculateRawTimes(
            2026, 11, 21,
            24.8607, 67.0011,
            'Karachi', 'Hanafi', 'MiddleOfTheNight', 'Asia/Karachi'
        );

        // ISNA method with Standard (Shafi'i) Asr
        $isnaTimes = $service->calculateRawTimes(
            2026, 11, 21,
            24.8607, 67.0011,
            'ISNA', 'Standard', 'MiddleOfTheNight', 'Asia/Karachi'
        );

        // Fajr angle 18 (Karachi) vs 15 (ISNA) -> Karachi Fajr is earlier than ISNA Fajr
        $this->assertNotEquals($karachiTimes['fajr'], $isnaTimes['fajr']);

        // Hanafi Asr shadow 2x is later than Standard Asr shadow 1x
        $this->assertNotEquals($karachiTimes['asr'], $isnaTimes['asr']);
        $this->assertTrue($karachiTimes['asr'] > $isnaTimes['asr']);
    }

    public function test_manual_adjustments_are_applied(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);
        $settings = PrayerSetting::create([
            'user_id' => $user->id,
            'city' => 'Karachi',
            'latitude' => 24.8607,
            'longitude' => 67.0011,
            'timezone' => 'Asia/Karachi',
            'calculation_method' => 'Karachi',
            'asr_method' => 'Hanafi',
            'manual_adjustments' => ['fajr' => 5], // +5 minutes
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/prayers/times?date=2026-11-21');
        $response->assertStatus(200);

        $rawFajr = $response->json('calculated_times.fajr');
        $adjFajr = $response->json('adjusted_times.fajr');

        $rawTime = Carbon::parse("2026-11-21 {$rawFajr}");
        $adjTime = Carbon::parse("2026-11-21 {$adjFajr}");

        $this->assertEquals(5, $rawTime->diffInMinutes($adjTime));
    }

    public function test_mosque_timetable_override_takes_precedence(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Karachi']);

        // Override Fajr to 06:00
        PrayerOverride::create([
            'user_id' => $user->id,
            'date' => '2026-11-21',
            'fajr' => '06:00',
            'note' => 'Mosque Fajr Jamaat',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/prayers/times?date=2026-11-21');

        $response->assertStatus(200)
            ->assertJsonPath('effective_times.fajr', '06:00')
            ->assertJsonPath('overrides.has_any', true)
            ->assertJsonPath('overrides.by_prayer.fajr', true);
    }

    public function test_can_update_prayer_settings(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/London']);

        $response = $this->actingAs($user)->putJson('/api/v1/prayers/settings', [
            'city' => 'London',
            'country' => 'United Kingdom',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'timezone' => 'Europe/London',
            'calculation_method' => 'MWL',
            'asr_method' => 'Standard',
            'manual_adjustments' => ['fajr' => 2, 'dhuhr' => 1],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('settings.city', 'London')
            ->assertJsonPath('settings.calculation_method', 'MWL')
            ->assertJsonPath('settings.asr_method', 'Standard');

        $this->assertDatabaseHas('prayer_settings', [
            'user_id' => $user->id,
            'city' => 'London',
            'calculation_method' => 'MWL',
        ]);
    }

    public function test_can_record_prayer_completion_and_fetch_history(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/prayers/completions', [
            'prayer_name' => 'fajr',
            'date' => '2026-11-21',
            'status' => 'completed',
            'on_time' => true,
            'in_jamaat' => true,
            'notes' => 'Prayed with congregation at local mosque',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('completion.prayer_name', 'fajr')
            ->assertJsonPath('completion.in_jamaat', true);

        $this->assertDatabaseHas('prayer_completions', [
            'user_id' => $user->id,
            'prayer_name' => 'fajr',
            'date' => '2026-11-21',
            'status' => 'completed',
        ]);

        // History
        $histRes = $this->actingAs($user)->getJson('/api/v1/prayers/history?month=11&year=2026');
        $histRes->assertStatus(200)
            ->assertJsonPath('total_completed', 1)
            ->assertJsonCount(1, 'completions');
    }
}
