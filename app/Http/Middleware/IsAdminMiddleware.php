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
        // Cek nama role melalui relasi. Ini lebih mudah dibaca.
        if (Auth::check() && Auth::user()->role->name === 'admin') {
            return $next($request);
        }
        return response()->json(['message' => 'Forbidden: Requires admin access.'], 403);
    }
}
