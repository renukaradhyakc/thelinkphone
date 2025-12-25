<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class UserTrial extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'started_at', 'expires_at', 'consumed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed' => 'boolean',
    ];

    public function isActive(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
