<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->resolveToken($request);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    protected function resolveToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            return $token !== '' ? $token : null;
        }

        $bearer = $request->bearerToken();

        return $bearer !== null && $bearer !== '' ? $bearer : null;
    }
}
