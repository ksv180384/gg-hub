<?php

namespace App\Providers;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Contracts\Repositories\GameRepositoryInterface;
use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Repositories\Eloquent\EloquentCharacterRepository;
use App\Repositories\Eloquent\EloquentGameRepository;
use App\Repositories\Eloquent\EloquentGuildRepository;
use App\Repositories\Eloquent\EloquentLocalizationRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CharacterRepositoryInterface::class, EloquentCharacterRepository::class);
        $this->app->bind(GameRepositoryInterface::class, EloquentGameRepository::class);
        $this->app->bind(GuildRepositoryInterface::class, EloquentGuildRepository::class);
        $this->app->bind(LocalizationRepositoryInterface::class, EloquentLocalizationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::bind('tag', function (string $value) {
            return \Domains\Tag\Models\Tag::findOrFail($value);
        });
    }
}
