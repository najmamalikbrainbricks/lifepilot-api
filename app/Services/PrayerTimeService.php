<?php

namespace App\Services;

use App\Models\PrayerCompletion;
use App\Models\PrayerOverride;
use App\Models\PrayerSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PrayerTimeService
{
    // Calculation Method Presets (Fajr angle, Isha angle or minutes)
    public const METHODS = [
        'Karachi' => ['name' => 'University of Islamic Sciences, Karachi', 'fajr' => 18.0, 'isha' => 18.0],
        'MWL' => ['name' => 'Muslim World League', 'fajr' => 18.0, 'isha' => 17.0],
        'ISNA' => ['name' => 'Islamic Society of North America', 'fajr' => 15.0, 'isha' => 15.0],
        'Egypt' => ['name' => 'Egyptian General Authority of Survey', 'fajr' => 19.5, 'isha' => 17.5],
        'Makkah' => ['name' => 'Umm al-Qura University, Makkah', 'fajr' => 18.5, 'isha' => '90 min'],
        'Tehran' => ['name' => 'Institute of Geophysics, University of Tehran', 'fajr' => 17.7, 'maghrib' => 4.5, 'isha' => 14.0],
        'Jafari' => ['name' => 'Shia Ithna-Ashari, Leva Institute, Qum', 'fajr' => 16.0, 'maghrib' => 4.0, 'isha' => 14.0],
        'Gulf' => ['name' => 'Gulf Region', 'fajr' => 19.5, 'isha' => '90 min'],
        'Kuwait' => ['name' => 'Kuwait', 'fajr' => 18.0, 'isha' => 17.5],
        'Qatar' => ['name' => 'Qatar', 'fajr' => 18.0, 'isha' => '90 min'],
        'Singapore' => ['name' => 'MUIS Singapore', 'fajr' => 20.0, 'isha' => 18.0],
        'France' => ['name' => 'UOIF France', 'fajr' => 12.0, 'isha' => 12.0],
        'Turkey' => ['name' => 'Diyanet İşleri Başkanlığı, Turkey', 'fajr' => 18.0, 'isha' => 17.0],
    ];

    /**
     * Get or create default prayer settings for a user.
     */
    public function getSettingsForUser(User $user): PrayerSetting
    {
        return PrayerSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'city' => 'Karachi',
                'country' => 'Pakistan',
                'latitude' => 24.8607,
                'longitude' => 67.0011,
                'timezone' => $user->timezone ?: 'Asia/Karachi',
                'calculation_method' => 'Karachi',
                'asr_method' => 'Hanafi',
                'high_latitude_rule' => 'MiddleOfTheNight',
                'manual_adjustments' => [
                    'fajr' => 0,
                    'dhuhr' => 0,
                    'asr' => 0,
                    'maghrib' => 0,
                    'isha' => 0,
                ],
                'enabled_prayers' => [
                    'fajr' => true,
                    'dhuhr' => true,
                    'asr' => true,
                    'maghrib' => true,
                    'isha' => true,
                    'tahajjud' => true,
                ],
                'tahajjud_preference' => [
                    'enabled' => true,
                    'duration_minutes' => 30,
                    'preparation_minutes' => 10,
                    'offset_before_fajr' => 45,
                ],
            ]
        );
    }

    /**
     * Calculate prayer times for a user on a given date.
     * Returns raw calculated times, adjusted times, effective times (with mosque overrides),
     * and Tahajjud last-third-of-night window.
     */
    public function getTimesForDate(User $user, Carbon $date): array
    {
        $settings = $this->getSettingsForUser($user);
        $tz = $settings->timezone ?: $user->timezone ?: 'Asia/Karachi';

        $localDate = $date->copy()->timezone($tz);
        $nextDate = $localDate->copy()->addDay();

        // 1. Calculate raw times for today
        $rawTimes = $this->calculateRawTimes(
            $localDate->year,
            $localDate->month,
            $localDate->day,
            (float) $settings->latitude,
            (float) $settings->longitude,
            $settings->calculation_method,
            $settings->asr_method,
            $settings->high_latitude_rule,
            $tz
        );

        // 2. Calculate raw times for tomorrow to accurately compute night interval (Maghrib to next Fajr)
        $tomorrowRawTimes = $this->calculateRawTimes(
            $nextDate->year,
            $nextDate->month,
            $nextDate->day,
            (float) $settings->latitude,
            (float) $settings->longitude,
            $settings->calculation_method,
            $settings->asr_method,
            $settings->high_latitude_rule,
            $tz
        );

        // 3. Apply manual adjustments
        $adjustments = $settings->manual_adjustments ?? [];
        $adjustedTimes = [];
        foreach ($rawTimes as $key => $timeStr) {
            $adj = (int) ($adjustments[$key] ?? 0);
            $adjustedTimes[$key] = $this->addMinutesToTimeString($timeStr, $adj);
        }

        // 4. Check for mosque/jamaat timetable override for this date
        $override = PrayerOverride::where('user_id', $user->id)
            ->where(function ($q) use ($localDate) {
                $q->where('date', $localDate->format('Y-m-d'))
                  ->orWhereNull('date');
            })
            ->orderByRaw('date IS NULL ASC') // specific date takes priority over null (default)
            ->first();

        $effectiveTimes = [];
        $hasOverrides = [];

        foreach (['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'] as $prayer) {
            if ($override && ! empty($override->{$prayer})) {
                $effectiveTimes[$prayer] = $override->{$prayer};
                $hasOverrides[$prayer] = true;
            } else {
                $effectiveTimes[$prayer] = $adjustedTimes[$prayer] ?? $rawTimes[$prayer];
                $hasOverrides[$prayer] = false;
            }
        }

        // 5. Compute Tahajjud and Night Intervals
        // Night is from today's Maghrib to tomorrow's Fajr
        $todayMaghrib = Carbon::parse($localDate->format('Y-m-d') . ' ' . $effectiveTimes['maghrib'], $tz);
        $tomorrowFajr = Carbon::parse($nextDate->format('Y-m-d') . ' ' . ($tomorrowRawTimes['fajr'] ?? '05:30'), $tz);

        $nightDurationMinutes = $todayMaghrib->diffInMinutes($tomorrowFajr);
        $midnight = $todayMaghrib->copy()->addMinutes((int) round($nightDurationMinutes / 2));
        $lastThirdStart = $todayMaghrib->copy()->addMinutes((int) round(($nightDurationMinutes * 2) / 3));

        $tahajjudPref = $settings->tahajjud_preference ?? [];
        $tahajjudDuration = (int) ($tahajjudPref['duration_minutes'] ?? 30);
        $offsetBeforeFajr = (int) ($tahajjudPref['offset_before_fajr'] ?? 45);

        // Suggested Tahajjud wake time: either offset before Fajr or at last third
        $suggestedTahajjudTime = $tomorrowFajr->copy()->subMinutes($offsetBeforeFajr);

        // 6. Check existing prayer completion records
        $completions = PrayerCompletion::where('user_id', $user->id)
            ->where('date', $localDate->format('Y-m-d'))
            ->get()
            ->keyBy('prayer_name');

        return [
            'date' => $localDate->format('Y-m-d'),
            'date_formatted' => $localDate->format('l, F j, Y'),
            'timezone' => $tz,
            'location' => [
                'city' => $settings->city,
                'country' => $settings->country,
                'latitude' => (float) $settings->latitude,
                'longitude' => (float) $settings->longitude,
            ],
            'method' => [
                'key' => $settings->calculation_method,
                'name' => self::METHODS[$settings->calculation_method]['name'] ?? $settings->calculation_method,
                'asr' => $settings->asr_method,
            ],
            'calculated_times' => $rawTimes,
            'adjusted_times' => $adjustedTimes,
            'effective_times' => $effectiveTimes,
            'overrides' => [
                'has_any' => in_array(true, $hasOverrides, true),
                'by_prayer' => $hasOverrides,
                'note' => $override?->note,
            ],
            'tahajjud' => [
                'enabled' => (bool) ($tahajjudPref['enabled'] ?? true),
                'last_third_start' => $lastThirdStart->format('H:i'),
                'last_third_end' => $tomorrowFajr->format('H:i'),
                'midnight' => $midnight->format('H:i'),
                'suggested_start' => $suggestedTahajjudTime->format('H:i'),
                'duration_minutes' => $tahajjudDuration,
                'night_duration_hours' => round($nightDurationMinutes / 60, 1),
            ],
            'completions' => $completions->map(function ($c) {
                return [
                    'prayer_name' => $c->prayer_name,
                    'status' => $c->status,
                    'completed_at' => $c->completed_at?->toIso8601String(),
                    'on_time' => (bool) $c->on_time,
                    'in_jamaat' => (bool) $c->in_jamaat,
                    'notes' => $c->notes,
                ];
            }),
        ];
    }

    /**
     * Astronomical Prayer Calculation Algorithm (based on standard Meeus solar coordinates).
     */
    public function calculateRawTimes(
        int $year,
        int $month,
        int $day,
        float $latitude,
        float $longitude,
        string $methodKey,
        string $asrMethod,
        string $highLatRule,
        string $timezone
    ): array {
        $method = self::METHODS[$methodKey] ?? self::METHODS['Karachi'];
        $fajrAngle = (float) $method['fajr'];
        $ishaParam = $method['isha'];

        // Julian date at 0h UT
        $jd = $this->julianDate($year, $month, $day);

        // Timezone offset in hours
        $tzOffset = Carbon::create($year, $month, $day, 12, 0, 0, $timezone)->offsetHours;

        // Dhuhr: solar noon
        // Equation of time & solar declination
        $sunData = $this->sunPosition($jd + 0.5 - $longitude / 360.0);
        $eqt = $sunData['eqt']; // in minutes
        $decl = $sunData['decl']; // in degrees

        // Solar noon time in hours
        $dhuhrHours = 12.0 + $tzOffset - ($longitude / 15.0) - ($eqt / 60.0);

        // Sunrise & Sunset (sun angle is -0.833 degrees)
        $sunriseAngle = -0.833;
        $sunDuration = $this->hourAngle($sunriseAngle, $latitude, $decl);

        $sunriseHours = $dhuhrHours - $sunDuration;
        $sunsetHours = $dhuhrHours + $sunDuration;

        // Fajr
        $fajrDuration = $this->hourAngle(-$fajrAngle, $latitude, $decl);
        $fajrHours = $dhuhrHours - $fajrDuration;

        // Asr: shadow factor (1 for standard, 2 for Hanafi)
        $shadowFactor = (strtolower($asrMethod) === 'hanafi') ? 2.0 : 1.0;
        $asrAngle = $this->asrSunAngle($shadowFactor, $latitude, $decl);
        $asrDuration = $this->hourAngle($asrAngle, $latitude, $decl);
        $asrHours = $dhuhrHours + $asrDuration;

        // Maghrib
        if (isset($method['maghrib'])) {
            $maghribDuration = $this->hourAngle(-(float) $method['maghrib'], $latitude, $decl);
            $maghribHours = $dhuhrHours + $maghribDuration;
        } else {
            $maghribHours = $sunsetHours;
        }

        // Isha
        if (is_numeric($ishaParam)) {
            $ishaDuration = $this->hourAngle(-(float) $ishaParam, $latitude, $decl);
            $ishaHours = $dhuhrHours + $ishaDuration;
        } elseif (is_string($ishaParam) && str_contains($ishaParam, 'min')) {
            $mins = (int) filter_var($ishaParam, FILTER_SANITIZE_NUMBER_INT);
            $ishaHours = $maghribHours + ($mins / 60.0);
        } else {
            $ishaHours = $maghribHours + 1.5;
        }

        return [
            'fajr' => $this->hoursToTimeString($fajrHours),
            'sunrise' => $this->hoursToTimeString($sunriseHours),
            'dhuhr' => $this->hoursToTimeString($dhuhrHours),
            'asr' => $this->hoursToTimeString($asrHours),
            'maghrib' => $this->hoursToTimeString($maghribHours),
            'isha' => $this->hoursToTimeString($ishaHours),
        ];
    }

    private function julianDate(int $year, int $month, int $day): float
    {
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        $a = floor($year / 100);
        $b = 2 - $a + floor($a / 4);
        return floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $b - 1524.5;
    }

    private function sunPosition(float $jd): array
    {
        $d = $jd - 2451545.0;
        $g = fmod(357.529 + 0.98560028 * $d, 360.0);
        $q = fmod(280.459 + 0.98564736 * $d, 360.0);
        $l = fmod($q + 1.915 * sin(deg2rad($g)) + 0.020 * sin(deg2rad(2 * $g)), 360.0);

        $e = 23.439 - 0.00000036 * $d;
        $ra = rad2deg(atan2(cos(deg2rad($e)) * sin(deg2rad($l)), cos(deg2rad($l)))) / 15.0;
        $ra = fmod($ra + 24.0, 24.0);

        $decl = rad2deg(asin(sin(deg2rad($e)) * sin(deg2rad($l))));
        $eqt = ($q / 15.0 - $ra) * 60.0; // Equation of time in minutes

        return ['decl' => $decl, 'eqt' => $eqt];
    }

    private function hourAngle(float $angle, float $lat, float $decl): float
    {
        $val = (sin(deg2rad($angle)) - sin(deg2rad($lat)) * sin(deg2rad($decl))) /
               (cos(deg2rad($lat)) * cos(deg2rad($decl)));

        // Clamp to [-1, 1] for extreme latitudes
        $val = max(-1.0, min(1.0, $val));
        return rad2deg(acos($val)) / 15.0;
    }

    private function asrSunAngle(float $shadowFactor, float $lat, float $decl): float
    {
        // angle = -arccot(shadowFactor + tan(|lat - decl|))
        $delta = abs($lat - $decl);
        $cotAngle = $shadowFactor + tan(deg2rad($delta));
        return rad2deg(atan(1.0 / $cotAngle));
    }

    private function hoursToTimeString(float $hours): string
    {
        // Normalize to 0-24
        $hours = fmod(fmod($hours, 24.0) + 24.0, 24.0);
        $h = floor($hours);
        $m = round(($hours - $h) * 60);

        if ($m >= 60) {
            $h += 1;
            $m = 0;
        }
        $h = fmod($h, 24);

        return sprintf('%02d:%02d', (int) $h, (int) $m);
    }

    private function addMinutesToTimeString(string $timeStr, int $minutes): string
    {
        if ($minutes === 0) return $timeStr;
        $parts = explode(':', $timeStr);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        $totalMinutes = ($h * 60 + $m + $minutes) % 1440;
        if ($totalMinutes < 0) $totalMinutes += 1440;

        $newH = floor($totalMinutes / 60);
        $newM = $totalMinutes % 60;

        return sprintf('%02d:%02d', (int) $newH, (int) $newM);
    }
}
