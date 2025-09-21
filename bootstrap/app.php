<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\IsAdminMiddleware; // <-- 1. Tambahkan use statement ini

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Tambahkan baris ini untuk middleware API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // TAMBAHKAN BLOK INI UNTUK MEMBUAT ALIAS
        $middleware->alias([
            'auth.api' => \Illuminate\Auth\Middleware\Authenticate::class . ':sanctum',
            'is.admin' => IsAdminMiddleware::class, // <-- 2. Daftarkan alias baru di sini
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();