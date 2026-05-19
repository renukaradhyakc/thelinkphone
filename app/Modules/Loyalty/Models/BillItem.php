<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    protected $table = 'bill_items';

    protected $fillable = [
        'bill_id',
        'provider',

        'description',
        'raw_description',

        'brand',
        'category',
        'categories',

        'item_type',
        'line_type',

        'hsn_code',
        'sku',
        'upc',
        'unit',

        'quantity',
        'unit_price',
        'line_total',
        'computed_total',

        'discount',
        'tax_rate',
        'tax_amount',

        'is_eligible',
        'points_earned',

        'confidence',
        'raw_meta',

        'position',
    ];

    protected $casts = [
        'categories' => 'array',
        'raw_meta' => 'array',

        'quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
        'computed_total' => 'float',

        'discount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',

        'is_eligible' => 'boolean',
        'confidence' => 'float',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}