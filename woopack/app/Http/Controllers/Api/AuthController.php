<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $configuredPassword = (string) Config::get('woopack.admin_password', 'admin');

        if (! hash_equals($configuredPassword, $validated['password'])) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('woopack_authenticated', true);

        return response()->json(['success' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('woopack_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    public function check(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => (bool) $request->session()->get('woopack_authenticated', false),
        ]);
    }
}
