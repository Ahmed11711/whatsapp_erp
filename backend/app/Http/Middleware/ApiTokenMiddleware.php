<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple API token middleware.
 *
 * This avoids pulling in extra packages and keeps the example
 * easy to run. It expects a header:
 *
 *   Authorization: Bearer {api_token}
 *
 * and attaches the matching user to the request.
 */
class ApiTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = substr($header, 7);

        /** @var \App\Models\User|null $user */
        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Attach the authenticated user to the request
        auth()->setUser($user);

        return $next($request);
    }
}

