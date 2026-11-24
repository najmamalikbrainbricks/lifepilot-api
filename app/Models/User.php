<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    public function prayerSetting(): HasOne
    {
        return $this->hasOne(PrayerSetting::class);
    }

    public function prayerOverrides(): HasMany
    {
        return $this->hasMany(PrayerOverride::class);
    }

    public function prayerCompletions(): HasMany
    {
        return $this->hasMany(PrayerCompletion::class);
    }

    public function protectedTimeBlocks(): HasMany
    {
        return $this->hasMany(ProtectedTimeBlock::class);
    }

    public function evidenceSubmissions(): HasMany
    {
        return $this->hasMany(EvidenceSubmission::class);
    }

    public function alarmEvents(): HasMany
    {
        return $this->hasMany(AlarmEvent::class);
    }

    public function timerSessions(): HasMany
    {
        return $this->hasMany(TimerSession::class);
    }
}
