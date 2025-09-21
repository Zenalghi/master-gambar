<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Periksa apakah user sudah login DAN perannya adalah 'admin'
        if (Auth::check() && Auth::user()->role === 'admin') {
            // Jika ya, izinkan request untuk melanjutkan
            return $next($request);
        }

        // Jika tidak, tolak akses dengan pesan error 403 Forbidden
        return response()->json(['message' => 'Forbidden: Requires admin access.'], 403);
    }
}

