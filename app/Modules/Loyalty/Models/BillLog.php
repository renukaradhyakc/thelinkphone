<?php

namespace App\Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

class BillLog extends Model
{
    protected $fillable = [
        'bill_id',
        'status',
        'message',
    ];
}