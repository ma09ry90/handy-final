<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if user is authenticated (via Sanctum Token)
        // Note: $request->user() is the standard way for API auth
        if ($request->user()) {
            
            // 2. Check if account status is NOT active
            if ($request->user()->account_status !== 'active') {
                
                // Optional: Revoke the token so they can't use it again
                // $request->user()->currentAccessToken()->delete();

                // Return JSON Error
                return response()->json([
                    'message' => 'Your account is currently ' . $request->user()->account_status . '. Please contact support.'
                ], 403); // 403 Forbidden
            }
        }

        return $next($request);
    }
}