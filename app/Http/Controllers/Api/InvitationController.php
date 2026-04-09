<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::query()->where('token', $token)->first();

        if (! $invitation || ! $invitation->isActive()) {
            return response()->json(['error' => 'Convite invalido ou expirado.'], 404);
        }

        return response()->json([
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $invitation = Invitation::query()->create([
            'email' => Str::lower($validated['email']),
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'invitation' => [
                'email' => $invitation->email,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at,
                'accept_url' => url("/invite/{$invitation->token}"),
            ],
        ], 201);
    }

    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $invitation = Invitation::query()->where('token', $validated['token'])->first();

        if (! $invitation || ! $invitation->isActive()) {
            return response()->json(['error' => 'Convite invalido ou expirado.'], 422);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return response()->json(['error' => 'Ja existe uma conta para este e-mail.'], 422);
        }

        $user = DB::transaction(function () use ($validated, $invitation): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'is_admin' => false,
            ]);

            $invitation->forceFill([
                'accepted_at' => now(),
            ])->save();

            return $user;
        });

        auth()->login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'has_integration' => false,
            'is_admin' => false,
        ], 201);
    }
}
