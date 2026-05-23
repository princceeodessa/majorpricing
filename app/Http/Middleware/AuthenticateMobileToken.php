<?php

namespace App\Http\Middleware;

use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! is_string($plainToken) || trim($plainToken) === '') {
            return $this->unauthorized();
        }

        $token = MobileAccessToken::query()
            ->with('user')
            ->where('token_hash', MobileAccessToken::hashPlainToken($plainToken))
            ->first();

        if (! $token || ! $token->isUsable() || ! $token->user || ! $token->user->is_active) {
            return $this->unauthorized();
        }

        $user = $token->user;

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile_access_token', $token);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Необходима авторизация.',
        ], 401);
    }
}
