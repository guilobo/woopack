<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'business_id',
    'waba_id',
    'phone_number_id',
    'display_phone_number',
    'verified_name',
    'quality_rating',
    'access_token',
    'token_expires_at',
])]
class WhatsAppConnection extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

