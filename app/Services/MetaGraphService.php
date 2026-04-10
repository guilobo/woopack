<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class MetaGraphService
{
    private function graphVersion(): string
    {
        return (string) config('woopack.meta_graph_version', 'v25.0');
    }

    private function appId(): string
    {
        return (string) config('woopack.meta_app_id', '');
    }

    private function appSecret(): string
    {
        return (string) config('woopack.meta_app_secret', '');
    }

    private function ensureConfigured(): void
    {
        if ($this->appId() === '' || $this->appSecret() === '') {
            throw new MetaGraphException('Meta app is not configured.', 500);
        }
    }

    public function exchangeAuthorizationCode(string $authorizationCode, string $redirectUri): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$this->graphVersion()}/oauth/access_token", [
                    'client_id' => $this->appId(),
                    'client_secret' => $this->appSecret(),
                    'redirect_uri' => $redirectUri,
                    'code' => $authorizationCode,
                ]);
        } catch (ConnectionException $exception) {
            throw new MetaGraphException('Failed to connect to Meta Graph API.', 500, $exception);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message')
                ?: ($response->body() ?: 'Failed to exchange authorization code.');

            throw new MetaGraphException($message, $response->status() ?: 500);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_string($payload['access_token'] ?? null)) {
            throw new MetaGraphException('Invalid Meta token response.', 500);
        }

        return [
            'access_token' => (string) $payload['access_token'],
            'token_type' => (string) ($payload['token_type'] ?? 'bearer'),
            'expires_in' => is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : null,
        ];
    }

    public function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $this->ensureConfigured();

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$this->graphVersion()}/oauth/access_token", [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $this->appId(),
                    'client_secret' => $this->appSecret(),
                    'fb_exchange_token' => $shortLivedToken,
                ]);
        } catch (ConnectionException $exception) {
            throw new MetaGraphException('Failed to connect to Meta Graph API.', 500, $exception);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message')
                ?: ($response->body() ?: 'Failed to exchange token.');

            throw new MetaGraphException($message, $response->status() ?: 500);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_string($payload['access_token'] ?? null)) {
            throw new MetaGraphException('Invalid Meta token response.', 500);
        }

        return [
            'access_token' => (string) $payload['access_token'],
            'token_type' => (string) ($payload['token_type'] ?? 'bearer'),
            'expires_in' => is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : null,
        ];
    }

    public function getPhoneNumber(string $phoneNumberId, string $accessToken): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$this->graphVersion()}/{$phoneNumberId}", [
                    'access_token' => $accessToken,
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);
        } catch (ConnectionException $exception) {
            throw new MetaGraphException('Failed to connect to Meta Graph API.', 500, $exception);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message')
                ?: ($response->body() ?: 'Failed to fetch phone number details.');

            throw new MetaGraphException($message, $response->status() ?: 500);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new MetaGraphException('Invalid Meta response.', 500);
        }

        return [
            'display_phone_number' => is_string($payload['display_phone_number'] ?? null) ? $payload['display_phone_number'] : null,
            'verified_name' => is_string($payload['verified_name'] ?? null) ? $payload['verified_name'] : null,
            'quality_rating' => is_string($payload['quality_rating'] ?? null) ? $payload['quality_rating'] : null,
        ];
    }

    public function expiresAtFromSeconds(?int $expiresIn): ?Carbon
    {
        if (! $expiresIn || $expiresIn <= 0) {
            return null;
        }

        return now()->addSeconds($expiresIn);
    }
}

