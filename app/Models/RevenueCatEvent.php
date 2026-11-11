<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 */
class RevenueCatEvent extends Model
{
    /**
     * @var string
     */
    protected $table = 'revenuecat_events';

    /**
     * @var string[]
     */
    protected $fillable = [
        'event_id',
        'type',
        'app_user_id',
        'user_id',
        'payload',
        'processed_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}