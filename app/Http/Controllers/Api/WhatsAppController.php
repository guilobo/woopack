<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MetaGraphException;
use App\Services\MetaGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppController extends Controller
{
    public function __construct(private readonly MetaGraphService $metaGraph)
    {
    }

    public function embeddedConfig(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $appId = (string) config('woopack.meta_app_id');
        $configId = (string) config('woopack.meta_wa_config_id');
        $graphVersion = (string) config('woopack.meta_graph_version', 'v25.0');

        if ($appId === '' || $configId === '') {
            return response()->json([
                'error' => 'WhatsApp Embedded Signup is not configured.',
            ], 500);
        }

        $state = Str::uuid()->toString();
        $redirectUri = route('meta.callback');
        $scopes = [
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];

        $request->session()->put('meta_oauth', [
            'state' => $state,
            'user_id' => $user->id,
            'created_at' => now()->toIso8601String(),
        ]);

        $request->session()->forget('meta_oauth_result');

        return response()->json([
            'app_id' => $appId,
            'config_id' => $configId,
            'graph_version' => $graphVersion,
            'origin' => url('/'),
            'user_id' => $user->id,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'auth_url' => sprintf(
                'https://www.facebook.com/%s/dialog/oauth?%s',
                $graphVersion,
                http_build_query([
                    'client_id' => $appId,
                    'redirect_uri' => $redirectUri,
                    'state' => $state,
                    'scope' => implode(',', $scopes),
                    'response_type' => 'code',
                    'override_default_response_type' => 'true',
                    'config_id' => $configId,
                    'extras' => json_encode([
                        'sessionInfoVersion' => '3',
                        'version' => 'v4',
                        'featureType' => 'whatsapp_business_app_onboarding',
                    ], JSON_UNESCAPED_SLASHES),
                ])
            ),
        ]);
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
            'authorization_code' => ['required_without:access_token', 'string', 'max:2048'],
            'access_token' => ['required_without:authorization_code', 'string', 'max:4096'],
            'expires_in' => ['nullable', 'integer', 'min:0'],
            'business_id' => ['nullable', 'string', 'max:64'],
            'waba_id' => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['required_with:authorization_code,access_token', 'string', 'max:64'],
        ]);

        try {
            $token = null;
            $expiresAt = null;

            if (filled($validated['access_token'] ?? null)) {
                $token = [
                    'access_token' => (string) $validated['access_token'],
                    'expires_in' => is_numeric($validated['expires_in'] ?? null) ? (int) $validated['expires_in'] : null,
                ];
            } else {
                $authorizationCode = (string) $validated['authorization_code'];
                $candidateRedirectUris = [
                    // Embedded Signup / JS SDK flow.
                    'https://www.facebook.com/connect/login_success.html',
                    // Server-side OAuth flow used in other parts of the app.
                    route('meta.callback'),
                ];

                $lastException = null;
                foreach ($candidateRedirectUris as $redirectUri) {
                    try {
                        $token = $this->metaGraph->exchangeAuthorizationCode($authorizationCode, $redirectUri);
                        $lastException = null;
                        break;
                    } catch (MetaGraphException $exception) {
                        $lastException = $exception;

                        // Most "wrong redirect" issues come back as 400 with messages about redirect_uri/app domains.
                        $msg = strtolower($exception->getMessage());
                        $isRedirectIssue = $exception->status() === 400
                            && (
                                str_contains($msg, 'redirect_uri')
                                || str_contains($msg, "can't load url")
                                || str_contains($msg, 'app domains')
                                || str_contains($msg, "isn't included in the app's domains")
                                || str_contains($msg, 'domain of this url')
                            );

                        if (! $isRedirectIssue) {
                            throw $exception;
                        }
                    }
                }

                if (! is_array($token)) {
                    throw $lastException ?: new MetaGraphException('Failed to exchange authorization code.', 500);
                }

                try {
                    $token = $this->metaGraph->exchangeForLongLivedToken($token['access_token']);
                } catch (MetaGraphException) {
                    // Keep short-lived token if exchange fails.
                }
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
