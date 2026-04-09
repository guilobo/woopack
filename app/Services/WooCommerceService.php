<?php

namespace App\Services;

use App\Models\User;
use App\Models\WooCommerceConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WooCommerceService
{
    public function getOrders(User $user, array $filters = []): array
    {
        return $this->request($this->resolveConnection($user), 'orders', $filters, 15);
    }

    public function getAllOrders(User $user, array $filters = [], int $perPage = 100): array
    {
        $connection = $this->resolveConnection($user);
        $page = 1;
        $orders = [];

        do {
            $batch = $this->request($connection, 'orders', [
                ...$filters,
                'page' => $page,
                'per_page' => $perPage,
            ], 15);

            $orders = [...$orders, ...$batch];
            $page++;
        } while (count($batch) === $perPage);

        return $orders;
    }

    public function getOrder(User $user, int|string $orderId): array
    {
        return $this->request($this->resolveConnection($user), "orders/{$orderId}", [], 10);
    }

    public function testConnection(User $user, array $overrides = []): array
    {
        $connection = $this->resolveConnection($user);

        $storeUrl = $overrides['store_url'] ?? $connection->store_url;
        $consumerKey = $overrides['consumer_key'] ?? $connection->consumer_key;
        $consumerSecret = $overrides['consumer_secret'] ?? $connection->consumer_secret;

        if (! filled($storeUrl) || ! filled($consumerKey) || ! filled($consumerSecret)) {
            throw new WooCommerceException('WooCommerce connection not configured', 400);
        }

        $temporaryConnection = new WooCommerceConnection([
            'store_url' => $storeUrl,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ]);

        $this->request($temporaryConnection, 'orders', [
            'page' => 1,
            'per_page' => 1,
        ], 10);

        return [
            'success' => true,
            'message' => 'Conexao WooCommerce validada com sucesso.',
        ];
    }

    protected function request(WooCommerceConnection $connection, string $endpoint, array $params, int $timeout): array
    {
        $baseUrl = $this->normalizeBaseUrl($connection->store_url);
        $query = array_filter([
            'consumer_key' => $connection->consumer_key,
            'consumer_secret' => $connection->consumer_secret,
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

    private function resolveConnection(User $user): WooCommerceConnection
    {
        $connection = $user->wooCommerceConnection()->first();

        if (! $connection) {
            throw new WooCommerceException('WooCommerce connection not configured', 400);
        }

        return $connection;
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new WooCommerceException('WooCommerce connection not configured', 400);
        }

        $url = rtrim($url, '/');

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = "https://{$url}";
        }

        return $url;
    }
}
