<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Pengecekan: Apakah user sudah login DAN nama rolenya adalah 'admin'?
        if (Auth::check() && Auth::user()->role->name === 'admin') {
            return $next($request);
        }

        // Jika tidak, tolak akses
        return response()->json(['message' => 'Forbidden: Requires admin access.'], 403);
    }
}
