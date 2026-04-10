<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MetaGraphException;
use App\Services\MetaGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(private readonly MetaGraphService $metaGraph)
    {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('whatsAppConnection');

        $connection = $user->whatsAppConnection;

        return response()->json([
            'connection' => $connection ? [
                'business_id' => $connection->business_id,
                'waba_id' => $connection->waba_id,
                'phone_number_id' => $connection->phone_number_id,
                'display_phone_number' => $connection->display_phone_number,
                'verified_name' => $connection->verified_name,
                'quality_rating' => $connection->quality_rating,
                'has_access_token' => filled($connection->access_token),
                'masked_access_token' => $this->maskToken($connection->access_token),
                'token_expires_at' => $connection->token_expires_at,
                'updated_at' => $connection->updated_at,
            ] : null,
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'authorization_code' => ['required', 'string', 'max:2048'],
            'business_id' => ['nullable', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $redirectUri = route('meta.callback');
            $short = $this->metaGraph->exchangeAuthorizationCode($validated['authorization_code'], $redirectUri);

            $token = $short;
            try {
                $token = $this->metaGraph->exchangeForLongLivedToken($short['access_token']);
            } catch (MetaGraphException) {
                // Keep short-lived token if exchange fails.
            }

            $expiresAt = $this->metaGraph->expiresAtFromSeconds($token['expires_in'] ?? null);

            $connection = $user->whatsAppConnection()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_id' => $validated['business_id'] ?? null,
                    'waba_id' => $validated['waba_id'] ?? null,
                    'phone_number_id' => $validated['phone_number_id'] ?? null,
                    'access_token' => $token['access_token'],
                    'token_expires_at' => $expiresAt,
                ],
            );

            if (filled($connection->phone_number_id)) {
                $details = $this->metaGraph->getPhoneNumber($connection->phone_number_id, $connection->access_token ?? '');
                $connection->fill($details);
                $connection->save();
            }

            return response()->json([
                'success' => true,
                'connection' => [
                    'business_id' => $connection->business_id,
                    'waba_id' => $connection->waba_id,
                    'phone_number_id' => $connection->phone_number_id,
                    'display_phone_number' => $connection->display_phone_number,
                    'verified_name' => $connection->verified_name,
                    'quality_rating' => $connection->quality_rating,
                    'has_access_token' => true,
                    'masked_access_token' => $this->maskToken($connection->access_token),
                    'token_expires_at' => $connection->token_expires_at,
                    'updated_at' => $connection->updated_at,
                ],
            ]);
        } catch (MetaGraphException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function test(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('whatsAppConnection');
        $connection = $user->whatsAppConnection;

        if (! $connection || ! filled($connection->access_token) || ! filled($connection->phone_number_id)) {
            return response()->json(['error' => 'WhatsApp connection not configured'], 400);
        }

        try {
            $details = $this->metaGraph->getPhoneNumber($connection->phone_number_id, $connection->access_token);
            $connection->fill($details);
            $connection->save();

            return response()->json([
                'success' => true,
                'message' => 'Conexao WhatsApp validada com sucesso.',
                'phone' => $details,
            ]);
        } catch (MetaGraphException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->status());
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->whatsAppConnection()->delete();

        return response()->json(['success' => true]);
    }

    private function maskToken(?string $token): ?string
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $suffix = substr($token, -8);

        return "***{$suffix}";
    }
}

