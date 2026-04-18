<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'ensure.not.banned' => \App\Http\Middleware\EnsureUserNotBanned::class,
            'permission' => \App\Http\Middleware\EnsureUserHasPermission::class,
            'permission.roles-permissions' => \App\Http\Middleware\EnsureUserCanUpdateUserRolesPermissions::class,
            'guild.member' => \App\Http\Middleware\EnsureUserIsGuildMember::class,
            'guild.role.permission' => \App\Http\Middleware\EnsureUserHasGuildRolePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $driverCode = $e->errorInfo[1] ?? null;
            $message = 'Не удалось сохранить данные. Попробуйте ещё раз или обратитесь к администратору.';

            if ($driverCode === 1062) {
                $message = 'Такая запись уже существует. Измените данные и повторите попытку.';
            } elseif ($driverCode === 1451 || $driverCode === 1452) {
                $message = 'Операция невозможна из‑за связанных записей.';
            }

            return response()->json(['message' => $message], 409);
        });
    })->create();
