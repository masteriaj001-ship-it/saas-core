<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $abilities = $request->abilities ?? ['*'];
        $token = $user->createToken($request->device_name, $abilities);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
        ]);
    }

    public function revokeCurrentToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revocado.']);
    }

    public function revokeAllTokens(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Todos los tokens revocados.']);
    }

    public function listTokens(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->tokens->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ])
        );
    }
}
