<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
      public function handle(Request $request, Closure $next)
    {
        // Check if the request has a token
        $token = $request->header('Authorization');

        if (!$token) {
            // Remember the intended URL
            $request->session()->put('url.intended', URL::full());

            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Assuming you are using Laravel Sanctum for token management
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            // Remember the intended URL
            $request->session()->put('url.intended', URL::full());

            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Optionally, set the authenticated user for the current request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
