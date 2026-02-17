<?php

use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GuildController;
use App\Http\Controllers\Api\LocalizationController;
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
Route::get('/php-info', function (){
    phpinfo();
});

Route::middleware(['auth'])->group(function () {
    Route::post('/games', [GameController::class, 'store'])->middleware('admin.subdomain');
    Route::post('/games/{game}', [GameController::class, 'update'])->middleware('admin.subdomain');
    Route::delete('/games/{game}', [GameController::class, 'destroy'])->middleware('admin.subdomain');
    Route::post('/games/{game}/localizations', [LocalizationController::class, 'store'])->middleware('admin.subdomain');
    Route::post('/guilds', [GuildController::class, 'store'])->middleware('admin.subdomain');
});

Route::get('/user', function (Request $request) {
    return $request->user();
});
