<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateJwt
{
    public function __construct(private TokenService $tokens) {}

    public function handle(Request $request, Closure $next)
    {
        $session = $this->tokens->authenticate($request->bearerToken());
        $request->setUserResolver(fn () => $session->user);
        Auth::setUser($session->user);
        $request->attributes->set('auth_session', $session);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
