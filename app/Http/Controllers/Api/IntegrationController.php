<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceException;
use App\Services\WooCommerceService;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
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
        $connection = $user->wooCommerceConnection;

        if (! $connection) {
            return response()->json([
                'connection' => null,
            ]);
        }

        try {
            $consumerKey = (string) $connection->consumer_key;
            $consumerSecret = (string) $connection->consumer_secret;
        } catch (DecryptException) {
            // Connection exists but was encrypted with a different APP_KEY.
            return response()->json([
                'connection' => [
                    'store_url' => $connection->store_url,
                    'has_consumer_key' => false,
                    'has_consumer_secret' => false,
                    'masked_consumer_key' => null,
                    'masked_consumer_secret' => null,
                    'updated_at' => $connection->updated_at,
                    'corrupted_credentials' => true,
                ],
            ]);
        }

        return response()->json([
            'connection' => [
                'store_url' => $connection->store_url,
                'has_consumer_key' => filled($consumerKey),
                'has_consumer_secret' => filled($consumerSecret),
                'masked_consumer_key' => $this->maskCredential($consumerKey),
                'masked_consumer_secret' => $this->maskCredential($consumerSecret),
                'updated_at' => $connection->updated_at,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $existingConnection = $user->wooCommerceConnection()->first();
        $credentialsCorrupted = false;

        $existingConsumerKey = null;
        $existingConsumerSecret = null;

        if ($existingConnection) {
            try {
                $existingConsumerKey = $existingConnection->consumer_key;
                $existingConsumerSecret = $existingConnection->consumer_secret;
            } catch (DecryptException) {
                $credentialsCorrupted = true;
            }
        }

        $validated = $request->validate([
            'store_url' => ['required', 'string', 'max:255'],
            'consumer_key' => [$existingConnection && ! $credentialsCorrupted ? 'nullable' : 'required', 'string', 'max:255'],
            'consumer_secret' => [$existingConnection && ! $credentialsCorrupted ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        $consumerKey = filled($validated['consumer_key'] ?? null)
            ? $validated['consumer_key']
            : $existingConsumerKey;
        $consumerSecret = filled($validated['consumer_secret'] ?? null)
            ? $validated['consumer_secret']
            : $existingConsumerSecret;

        if (! filled($consumerKey) || ! filled($consumerSecret)) {
            return response()->json([
                'error' => 'Informe consumer key e consumer secret para salvar a integracao.',
            ], 422);
        }

        if ($existingConnection) {
            $existingConnection->delete();
        }

        $connection = $user->wooCommerceConnection()->create([
            'store_url' => $validated['store_url'],
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ]);

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
