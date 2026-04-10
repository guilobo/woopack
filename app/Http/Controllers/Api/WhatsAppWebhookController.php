<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardWhatsAppWebhookJob;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));
        $expected = trim((string) config('woopack.whatsapp_webhook_verify_token'));

        Log::info('whatsapp.webhook.verify', [
            'mode' => $mode,
            'has_token' => $token !== '',
            'has_challenge' => $challenge !== '',
        ]);

        if ($mode !== 'subscribe' || $token === '' || $expected === '' || ! hash_equals($expected, $token)) {
            return response('Invalid verify token.', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        $eventType = $this->detectEventType($payload);
        $phoneNumberId = $this->extractPhoneNumberId($payload);
        $connection = null;

        if ($phoneNumberId !== null) {
            $connection = WhatsAppConnection::query()
                ->where('phone_number_id', $phoneNumberId)
                ->first();
        }

        Log::info('whatsapp.webhook.received', [
            'event_type' => $eventType,
            'phone_number_id' => $phoneNumberId,
            'user_id' => $connection?->user_id,
        ]);

        $forwardUrl = trim((string) config('woopack.whatsapp_webhook_forward_url'));

        $logEntry = WhatsAppWebhookLog::query()->create([
            'user_id' => $connection?->user_id,
            'phone_number_id' => $phoneNumberId,
            'event_type' => $eventType,
            'forward_status' => $forwardUrl !== '' ? 'queued' : 'disabled',
            'forward_url' => $forwardUrl !== '' ? $forwardUrl : null,
            'payload' => $payload,
        ]);

        if ($forwardUrl !== '') {
            Log::info('whatsapp.webhook.forward_dispatch', [
                'log_id' => $logEntry->id,
                'event_type' => $eventType,
                'phone_number_id' => $phoneNumberId,
                'user_id' => $connection?->user_id,
            ]);

            ForwardWhatsAppWebhookJob::dispatchAfterResponse($logEntry->id);
        }

        return response()->json([
            'success' => true,
            'received' => true,
        ]);
    }

    private function detectEventType(array $payload): string
    {
        foreach (($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }

                if (! empty($value['messages']) && is_array($value['messages'])) {
                    return 'messages';
                }

                if (! empty($value['statuses']) && is_array($value['statuses'])) {
                    return 'statuses';
                }
            }
        }

        return 'unknown';
    }

    private function extractPhoneNumberId(array $payload): ?string
    {
        foreach (($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (($entry['changes'] ?? []) as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $metadataPhoneId = data_get($change, 'value.metadata.phone_number_id');
                if (is_string($metadataPhoneId) && $metadataPhoneId !== '') {
                    return $metadataPhoneId;
                }
            }
        }

        return null;
    }
}
