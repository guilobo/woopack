<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceException;
use App\Services\WooCommerceService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(private readonly WooCommerceService $wooCommerce)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('wooCommerceConnection');

        return response()->json([
            'connection' => $user->wooCommerceConnection ? [
                'store_url' => $user->wooCommerceConnection->store_url,
                'has_consumer_key' => filled($user->wooCommerceConnection->consumer_key),
                'has_consumer_secret' => filled($user->wooCommerceConnection->consumer_secret),
                'masked_consumer_key' => $this->maskCredential($user->wooCommerceConnection->consumer_key),
                'masked_consumer_secret' => $this->maskCredential($user->wooCommerceConnection->consumer_secret),
                'updated_at' => $user->wooCommerceConnection->updated_at,
            ] : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $existingConnection = $user->wooCommerceConnection()->first();

        $validated = $request->validate([
            'store_url' => ['required', 'string', 'max:255'],
            'consumer_key' => [$existingConnection ? 'nullable' : 'required', 'string', 'max:255'],
            'consumer_secret' => [$existingConnection ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        $connection = $user->wooCommerceConnection()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'store_url' => $validated['store_url'],
                'consumer_key' => filled($validated['consumer_key'] ?? null)
                    ? $validated['consumer_key']
                    : $existingConnection?->consumer_key,
                'consumer_secret' => filled($validated['consumer_secret'] ?? null)
                    ? $validated['consumer_secret']
                    : $existingConnection?->consumer_secret,
            ],
        );

        return response()->json([
            'success' => true,
            'connection' => [
                'store_url' => $connection->store_url,
                'has_consumer_key' => true,
                'has_consumer_secret' => true,
                'masked_consumer_key' => $this->maskCredential($connection->consumer_key),
                'masked_consumer_secret' => $this->maskCredential($connection->consumer_secret),
                'updated_at' => $connection->updated_at,
            ],
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'store_url' => ['nullable', 'string', 'max:255'],
            'consumer_key' => ['nullable', 'string', 'max:255'],
            'consumer_secret' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json(
                $this->wooCommerce->testConnection($user, [
                    'store_url' => filled($validated['store_url'] ?? null) ? $validated['store_url'] : null,
                    'consumer_key' => filled($validated['consumer_key'] ?? null) ? $validated['consumer_key'] : null,
                    'consumer_secret' => filled($validated['consumer_secret'] ?? null) ? $validated['consumer_secret'] : null,
                ])
            );
        } catch (WooCommerceException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    private function maskCredential(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $suffix = substr($value, -6);

        return "***{$suffix}";
    }
}
