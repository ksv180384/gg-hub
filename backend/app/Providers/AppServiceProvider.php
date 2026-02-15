<?php

namespace App\Providers;

use App\Contracts\Repositories\GameRepositoryInterface;
use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Repositories\Eloquent\EloquentGameRepository;
use App\Repositories\Eloquent\EloquentLocalizationRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GameRepositoryInterface::class, EloquentGameRepository::class);
        $this->app->bind(LocalizationRepositoryInterface::class, EloquentLocalizationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
