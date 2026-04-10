<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'phone_number_id',
    'event_type',
    'forward_status',
    'forward_url',
    'forwarded_at',
    'forward_response_body',
    'payload',
])]
class WhatsAppWebhookLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'forwarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
