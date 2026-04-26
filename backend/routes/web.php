<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SitemapController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'yandex|vkontakte');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'yandex|vkontakte');

Route::get('/sitemap.xml', SitemapController::class);
