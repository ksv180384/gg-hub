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
        apiPrefix: '/api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Сессия для API (Fortify, auth через cookie)
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'admin.subdomain' => \App\Http\Middleware\EnsureAdminSubdomain::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'permission.roles-permissions' => \App\Http\Middleware\EnsureUserCanUpdateUserRolesPermissions::class,
            'guild.member' => \App\Http\Middleware\EnsureUserIsGuildMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
