<?php

namespace App\Models;

use Barryvdh\LaravelIdeHelper\Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\ZoomIntegration
 *
 * @property int $id
 * @property int $user_id
 * @property string $access_token
 * @property string|null $refresh_token
 * @property mixed $meta
 * @property string|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|ZoomIntegration newModelQuery()
 * @method static Builder|ZoomIntegration newQuery()
 * @method static Builder|ZoomIntegration query()
 * @method static Builder|ZoomIntegration whereAccessToken($value)
 * @method static Builder|ZoomIntegration whereRefreshToken($value)
 * @method static Builder|ZoomIntegration whereCreatedAt($value)
 * @method static Builder|ZoomIntegration whereId($value)
 * @method static Builder|ZoomIntegration whereLastUsedAt($value)
 * @method static Builder|ZoomIntegration whereMeta($value)
 * @method static Builder|ZoomIntegration whereUpdatedAt($value)
 * @method static Builder|ZoomIntegration whereUserId($value)
 *
 * @mixin Eloquent
 */
class ZoomIntegration extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'zoom_integrations';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'meta',
        'last_used_at',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'access_token' => 'string',
        'refresh_token' => 'string',
        'meta' => 'string',
        'last_used_at' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}