<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return new JsonResponse(['message' => 'Missing bearer token.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->jwtService->decodeToken($bearerToken);

        if (! $payload || ! isset($payload['sub'])) {
            return new JsonResponse(['message' => 'Invalid or expired token.'], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::query()->find($payload['sub']);

        if (! $user) {
            return new JsonResponse(['message' => 'Authenticated user no longer exists.'], Response::HTTP_UNAUTHORIZED);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
