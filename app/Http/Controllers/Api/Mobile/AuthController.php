<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\UserResource;
use App\Models\MobileAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::query()
            ->with('priceProfile')
            ->where('login', $data['login'])
            ->orWhere('email', $data['login'])
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Неверные учетные данные или доступ пользователя отключен.'],
            ]);
        }

        [$token, $plainToken] = MobileAccessToken::issueForUser($user, [
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null,
        ]);

        return response()->json([
            'token' => $plainToken,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        $request->user()->loadMissing('priceProfile');

        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_access_token');

        if ($token instanceof MobileAccessToken) {
            $token->revoke();
        }

        return response()->json(['ok' => true]);
    }
}
