<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookLog;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardWhatsAppWebhookJob
{
    use Dispatchable, Queueable;

    public function __construct(private readonly int $webhookLogId)
    {
    }

    public function handle(): void
    {
        $logEntry = WhatsAppWebhookLog::query()->find($this->webhookLogId);

        if (! $logEntry) {
            return;
        }

        $forwardUrl = trim((string) $logEntry->forward_url);
        if ($forwardUrl === '') {
            $logEntry->update([
                'forward_status' => 'disabled',
            ]);

            return;
        }

        $payload = is_array($logEntry->payload) ? $logEntry->payload : [];

        try {
            $response = Http::timeout(max(1, (int) config('woopack.whatsapp_webhook_forward_timeout', 5)))
                ->acceptJson()
                ->asJson()
                ->post($forwardUrl, [
                    'meta' => [
                        'source' => 'woopack',
                        'event_type' => $logEntry->event_type,
                        'received_at' => optional($logEntry->created_at)->toIso8601String(),
                    ],
                    'user_id' => $logEntry->user_id,
                    'phone_number_id' => $logEntry->phone_number_id,
                    'payload' => $payload,
                ]);

            $logEntry->update([
                'forward_status' => $response->successful() ? 'forwarded' : 'failed',
                'forwarded_at' => now(),
                'forward_response_body' => $response->body(),
            ]);

            Log::info('whatsapp.webhook.forwarded', [
                'log_id' => $logEntry->id,
                'user_id' => $logEntry->user_id,
                'event_type' => $logEntry->event_type,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $exception) {
            $logEntry->update([
                'forward_status' => 'failed',
                'forwarded_at' => now(),
                'forward_response_body' => $exception->getMessage(),
            ]);

            Log::warning('whatsapp.webhook.forward_failed', [
                'log_id' => $logEntry->id,
                'user_id' => $logEntry->user_id,
                'event_type' => $logEntry->event_type,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
