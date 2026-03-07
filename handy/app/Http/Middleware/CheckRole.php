<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Check if user is logged in
        if (!Auth::check()) {
            // API Response: Unauthenticated
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = Auth::user();

        $roleMap = [
            'buyer' => 1,
            'artisan' => 2,
            'delivery' => 3,
            'admin' => 4,
        ];

        $requiredRoleId = $roleMap[$role] ?? null;

        // 2. Check Role
        if ($user->role_id !== $requiredRoleId) {
            // API Response: Forbidden
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        return $next($request);
    } 
}