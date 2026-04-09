<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated, true)) {
            return response()->json(['error' => 'Credenciais invalidas.'], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            ...$this->authPayload($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function check(Request $request): JsonResponse
    {
        return response()->json($this->authPayload($request));
    }

    public function me(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($this->userPayload($request));
    }

    private function authPayload(Request $request): array
    {
        if (! Auth::check()) {
            return [
                'authenticated' => false,
                'user' => null,
                'has_integration' => false,
                'is_admin' => false,
            ];
        }

        return [
            'authenticated' => true,
            ...$this->userPayload($request),
        ];
    }

    private function userPayload(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->loadMissing('wooCommerceConnection');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'has_integration' => $user->wooCommerceConnection !== null,
            'is_admin' => (bool) $user->is_admin,
        ];
    }
}
