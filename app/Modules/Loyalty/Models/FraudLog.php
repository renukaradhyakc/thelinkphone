<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class FraudLog extends Model
{
    protected $fillable = [
        'bill_id',
        'user_id',
        'score',
        'reasons',
        'decision',
        'reviewed_by',
        'override_status',
    ];

    protected $casts = [
        'reasons' => 'array',
    ];
}