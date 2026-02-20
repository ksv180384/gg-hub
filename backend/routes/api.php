<?php

use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GuildController;
use App\Http\Controllers\Api\LocalizationController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PermissionGroupController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRolePermissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json(['message' => 'ok!!!']);
});

Route::get('/admin', function (Request $request) {
    return response()->json(['message' => 'admin']);
});

Route::get('/context', [ContextController::class, 'show']);

Route::get('/games', [GameController::class, 'index']);
Route::get('/games/{game}', [GameController::class, 'show']);
Route::get('/guilds', [GuildController::class, 'index']);

Route::get('/user', [UserController::class, 'show']);

Route::middleware(['auth'])->group(function () {

    Route::post('/user', [UserController::class, 'update']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::post('/guilds', [GuildController::class, 'store'])->middleware('admin.subdomain');

    Route::middleware(['admin.subdomain', 'permission:admnistrirovanie'])->group(function () {
        Route::get('/permission-groups', [PermissionGroupController::class, 'index']);
        Route::get('/permission-groups/{permission_group}', [PermissionGroupController::class, 'show']);
        Route::post('/permission-groups', [PermissionGroupController::class, 'store']);
        Route::put('/permission-groups/{permission_group}', [PermissionGroupController::class, 'update']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/permissions/{permission}', [PermissionController::class, 'show']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::put('/permissions/{permission}', [PermissionController::class, 'update']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::put('/users/{user}/roles-permissions', [UserRolePermissionController::class, 'update']);

        Route::post('/games', [GameController::class, 'store'])->middleware('permission:dobavliat-igru');
        Route::post('/games/{game}', [GameController::class, 'update'])->middleware('permission:redaktirovat-igru');
        Route::delete('/games/{game}', [GameController::class, 'destroy'])->middleware('permission:udaliat-igru');
        Route::post('/games/{game}/localizations', [LocalizationController::class, 'store'])->middleware('permission:dobavliat-lokalizaciia');

        Route::get('/games/{game}/localizations/{localization}/servers', [ServerController::class, 'index'])->middleware('permission:dobaliat-server');
        Route::post('/games/{game}/localizations/{localization}/servers', [ServerController::class, 'store'])->middleware('permission:dobaliat-server');
        Route::put('/servers/{server}', [ServerController::class, 'update'])->middleware('permission:redaktirovat-server');
        Route::delete('/servers/{server}', [ServerController::class, 'destroy'])->middleware('permission:udaliat-server');

        Route::post('/games/{game}/localizations/{localization}/servers/merge', [ServerController::class, 'merge'])->middleware('permission:obieediniat-servera');
    });
});
