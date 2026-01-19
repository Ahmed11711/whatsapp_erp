<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.api' => \App\Http\Middleware\ApiTokenMiddleware::class,
        ]);
        
        // Exclude webhook routes from CSRF protection
        // WhatsApp Business API webhooks don't include CSRF tokens
        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp*',
            'test/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
