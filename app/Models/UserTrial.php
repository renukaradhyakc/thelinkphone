<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserTrial extends Model
{
    use HasFactory;

    protected $table = 'user_trials';

    protected $fillable = [
        'user_id',
        'started_at',
        'expires_at',
        'consumed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed' => 'boolean',
    ];

    /**
     * Trial is active only when:
     * - started
     * - not expired
     * - not consumed
     */
    public function isActive(): bool
    {
        return
            $this->started_at !== null &&
            $this->expires_at !== null &&
            $this->expires_at->isFuture() &&
            $this->consumed === false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
