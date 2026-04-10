<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaAuthController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $appId = (string) config('woopack.meta_app_id');
        $graphVersion = (string) config('woopack.meta_graph_version', 'v25.0');

        if ($appId === '') {
            return response()->json([
                'error' => 'Meta app is not configured.',
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

        return response()->json([
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scopes' => $scopes,
            'auth_url' => sprintf(
                'https://www.facebook.com/%s/dialog/oauth?%s',
                $graphVersion,
                http_build_query([
                    'client_id' => $appId,
                    'redirect_uri' => $redirectUri,
                    'state' => $state,
                    'scope' => implode(',', $scopes),
                    'response_type' => 'code',
                ])
            ),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'result' => $request->session()->get('meta_oauth_result'),
        ]);
    }

    public function clearStatus(Request $request): JsonResponse
    {
        $request->session()->forget('meta_oauth_result');

        return response()->json(['success' => true]);
    }

    public function callback(Request $request)
    {
        $oauth = $request->session()->get('meta_oauth');
        $query = $request->query();
        $state = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');
        $error = (string) $request->query('error', '');
        $errorMessage = (string) $request->query('error_message', '');
        $businessId = (string) $request->query('business_id', '');
        $wabaId = (string) $request->query('waba_id', '');
        $phoneNumberId = (string) $request->query('phone_number_id', '');
        $displayPhoneNumber = (string) $request->query('display_phone_number', '');

        $matchesState = is_array($oauth)
            && hash_equals((string) Arr::get($oauth, 'state', ''), $state);

        $status = 'error';
        $message = 'Nao foi possivel validar o retorno da Meta.';

        if ($error !== '') {
            $message = $errorMessage !== '' ? $errorMessage : 'A autorizacao foi recusada ou interrompida.';
        } elseif ($matchesState && $code !== '') {
            $status = 'success';
            $message = 'Autorizacao recebida com sucesso. Voce ja pode voltar ao WooPack.';
        } elseif ($code === '') {
            $message = 'Nenhum codigo de autorizacao foi recebido.';
        } elseif (! $matchesState) {
            $message = 'O retorno da Meta nao corresponde a uma sessao valida do WooPack.';
        }

        $result = [
            'status' => $status,
            'message' => $message,
            'code' => $status === 'success' ? $code : null,
            'business_id' => $businessId !== '' ? $businessId : null,
            'waba_id' => $wabaId !== '' ? $wabaId : null,
            'phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
            'display_phone_number' => $displayPhoneNumber !== '' ? $displayPhoneNumber : null,
            'query_keys' => array_keys($query),
            'state' => $state !== '' ? $state : null,
            'error' => $error !== '' ? $error : null,
            'error_message' => $errorMessage !== '' ? $errorMessage : null,
        ];

        Log::info('meta.oauth.callback', [
            'status' => $status,
            'user_id' => Arr::get($oauth, 'user_id'),
            'matches_state' => $matchesState,
            'query_keys' => array_keys($query),
            'has_code' => $code !== '',
            'code_length' => strlen($code),
            'business_id' => $result['business_id'],
            'waba_id' => $result['waba_id'],
            'phone_number_id' => $result['phone_number_id'],
            'display_phone_number' => $result['display_phone_number'],
            'error' => $result['error'],
            'error_message' => $result['error_message'],
        ]);

        $request->session()->put('meta_oauth_result', $result);

        if ($status === 'success') {
            $request->session()->forget('meta_oauth');
        }

        return response()->view('meta-callback', [
            'result' => $result,
        ]);
    }
}
