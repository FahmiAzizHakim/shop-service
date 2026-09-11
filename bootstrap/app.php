<?php

use App\Exceptions\ApiExceptionHandler;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
 * shop-service speaks JSON only: there are no web routes and no session, so
 * every failure is rendered by ApiExceptionHandler rather than Laravel's HTML
 * error pages.
 *
 * Cookies are the exception to "stateless": a guest's basket is keyed on the
 * cart_token cookie, which is how a browser gets its own cart back without
 * logging in. The api group does not carry the cookie middleware by default,
 * so it is added here -- without it the token would never be readable and
 * every request would mint a new empty cart.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nothing reaches a route until this has passed: it is the check
        // that the request came from the gateway at all, and it applies to the
        // public routes as much as the admin ones. First in the group, so a
        // stranger is refused before a cookie is decrypted or a controller is
        // resolved.
        $middleware->api(prepend: [
            \App\Http\Middleware\VerifyGatewayToken::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
        ]);

        // Verifies the gateway's token and puts its claims on the request.
        // Every /api/admin route is wrapped in this; see app/Helpers/helpers.php
        // for how a controller reads the result.
        $middleware->alias([
            'jwt' => \App\Http\Middleware\VerifyJwt::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (Throwable $e, $request) => ApiExceptionHandler::render($e));
    })->create();
