<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row exists only when the user changed a category.
 * No row means the default from config/notifications.php.
 *
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property bool $push_enabled
 */
class NotificationPreference extends Model
{
    /**
     * @var string
     */
    protected $table = 'notification_preferences';

    /**
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'category',
        'push_enabled',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'user_id' => 'integer',
        'push_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}