<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PhoneSchedule extends Model
{
    use HasFactory;

    protected $table = 'phone_schedules';

    protected $with = ['user'];

    protected $fillable = [
        'user_id',
        'phone_number_normalized',
        'schedule_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'schedule_id' => 'integer',
        'phone_number_normalized' => 'string',
    ];

    /**
     * Owner of this phone schedule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Existing reusable schedule selected by user.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Custom timing rows.
     */
    public function userSchedules(): HasMany
    {
        return $this->hasMany(UserSchedule::class, 'phone_schedule_id');
    }

    public function otherPartyByPhone(): HasOne
    {
        return $this->hasOne(User::class, 'phone_number', 'phone_number_normalized');
    }
}