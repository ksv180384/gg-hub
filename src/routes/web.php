<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

//Route::domain('{subdomain?}.gg.local')->group(function () {


    Route::group(['prefix' => 'admin'], function ($subdomain) {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/skills', [\App\Http\Controllers\Admin\SkillController::class, 'index'])->name('admin.skills');
        Route::get('/skills/get-all-storage', [\App\Http\Controllers\Admin\SkillController::class, 'index']);
        Route::post('/skills/transfer-to-site', [\App\Http\Controllers\Admin\SkillController::class, 'transferToSite']);
        Route::post('/skills/transfer-to-site-all', [\App\Http\Controllers\Admin\SkillController::class, 'transferToSiteAll']);
        Route::post('/skills/translate', [\App\Http\Controllers\Admin\SkillController::class, 'translate']);
        Route::post('/skills/translate-all', [\App\Http\Controllers\Admin\SkillController::class, 'translateAll']);
    });

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::get('/', function () {
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    });

//});

require __DIR__.'/auth.php';
