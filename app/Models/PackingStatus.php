<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingStatus extends Model
{
    protected $fillable = [
        'woo_order_id',
        'packed_at',
    ];

    protected $casts = [
        'packed_at' => 'datetime',
    ];
}
