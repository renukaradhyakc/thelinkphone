<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyPoint extends Model
{
    use HasFactory;

    protected $table = 'loyalty_points';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'bill_id',
        'points',
    ];

    /**
     * Type casting
     */
    protected $casts = [
        'points' => 'integer',
    ];

    /**
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * Scopes (optional but useful)
     */

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Helper methods
     */

    public static function award(int $userId, int $billId, int $points): self
    {
        return self::create([
            'user_id' => $userId,
            'bill_id' => $billId,
            'points'  => $points,
        ]);
    }
}