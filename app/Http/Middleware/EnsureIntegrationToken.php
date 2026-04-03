<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationToken
{
    /**
     * @param  array<int, string>  ...$services
     */
    public function handle(Request $request, Closure $next, string ...$services): Response
    {
        $configuredTokens = collect(config('integrations.tokens', []))
            ->filter(fn ($token) => filled($token));

        $allowedTokens = $services === []
            ? $configuredTokens
            : $configuredTokens->only($services);

        $providedToken = $request->bearerToken()
            ?: $request->header((string) config('integrations.token_header', 'X-Integration-Key'));

        if (! filled($providedToken)) {
            return $this->unauthorized('Integration token is required.');
        }

        $matchedService = $allowedTokens
            ->keys()
            ->first(fn (string $service): bool => hash_equals((string) $allowedTokens->get($service), (string) $providedToken));

        if (! $matchedService) {
            return $this->unauthorized('Invalid integration token.');
        }

        $request->attributes->set('integration_service', $matchedService);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
