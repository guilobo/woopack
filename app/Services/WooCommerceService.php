<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WooCommerceService
{
    public function getOrders(array $filters = []): array
    {
        return $this->request('orders', $filters, 15);
    }

    public function getOrder(int|string $orderId): array
    {
        return $this->request("orders/{$orderId}", [], 10);
    }

    protected function request(string $endpoint, array $params, int $timeout): array
    {
        $baseUrl = $this->getBaseUrl();
        [$key, $secret] = $this->getCredentials();

        $query = array_filter([
            'consumer_key' => $key,
            'consumer_secret' => $secret,
            ...$params,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$baseUrl}/wp-json/wc/v3/{$endpoint}", $query);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('Failed to connect to WooCommerce', 500, $exception);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'message')
                ?: ($response->body() ?: 'Failed to fetch data from WooCommerce');

            throw new WooCommerceException($message, $response->status() ?: 500);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new WooCommerceException('Invalid WooCommerce response', 500);
        }

        return $payload;
    }

    protected function getBaseUrl(): string
    {
        $url = trim((string) config('woopack.woocommerce.url'));

        if ($url === '') {
            throw new WooCommerceException('WooCommerce URL not configured', 400);
        }

        $url = rtrim($url, '/');

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = "https://{$url}";
        }

        return $url;
    }

    protected function getCredentials(): array
    {
        $key = trim((string) config('woopack.woocommerce.key'));
        $secret = trim((string) config('woopack.woocommerce.secret'));

        if ($key === '' || $secret === '') {
            throw new WooCommerceException('WooCommerce credentials not configured', 400);
        }

        return [$key, $secret];
    }
}
