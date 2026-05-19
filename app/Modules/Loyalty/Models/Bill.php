<?php

namespace App\Modules\Loyalty\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bills';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'file_url',
        'status',
        'invoice_number',
        'vendor_name',
        'amount',
        'bill_date',
        'raw_text',
        'hash',
        'processed_at',
        'provider',
        'confidence',
        'semantic_hash',
        'reviewed_by', 
        'reviewed_at',
        'override_note',
    ];

    /**
     * Type casting
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'bill_date' => 'date',
        'processed_at' => 'datetime',
        'confidence' => 'float',
    ];

    /**
     * Status constants (avoid magic strings)
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_REVIEW = 'review';

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes (useful for querying)
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeDone($query)
    {
        return $query->where('status', self::STATUS_DONE);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Helper methods (cleaner than repeating logic)
     */
    public function markProcessing()
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markDone(array $data = [])
    {
        $this->update(array_merge($data, [
            'status' => self::STATUS_DONE,
            'processed_at' => now(),
        ]));
    }

    public function markFailed()
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    public function markDuplicate()
    {
        $this->update(['status' => self::STATUS_DUPLICATE]);
    }

    public function markInvalid()
    {
        $this->update(['status' => self::STATUS_INVALID]);
    }

    public function markReview()
    {
        $this->update(['status' => self::STATUS_REVIEW]);
    }

    public function markApprovedByAdmin($adminId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_DONE,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'override_note' => $note,
        ]);
    }

    public function markRejectedByAdmin($adminId, $note = null)
    {
        $this->update([
            'status' => self::STATUS_DUPLICATE,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'override_note' => $note,
        ]);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function loyaltyPoint()
    {
        return $this->hasOne(LoyaltyPoint::class);
    }
}