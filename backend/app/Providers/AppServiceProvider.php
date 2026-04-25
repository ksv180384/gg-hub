<?php

namespace App\Providers;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Contracts\Repositories\GameRepositoryInterface;
use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Models\Notification;
use App\Observers\NotificationObserver;
use App\Observers\PollObserver;
use Domains\Poll\Models\Poll;
use App\Repositories\Eloquent\EloquentCharacterRepository;
use App\Repositories\Eloquent\EloquentGameRepository;
use App\Repositories\Eloquent\EloquentGuildRepository;
use App\Repositories\Eloquent\EloquentLocalizationRepository;
use Illuminate\Support\Facades\Event;
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

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('yandex', \SocialiteProviders\Yandex\Provider::class);
            $event->extendSocialite('vkontakte', \SocialiteProviders\VKontakte\Provider::class);
        });

        Notification::observe(NotificationObserver::class);
        Poll::observe(PollObserver::class);
    }
}
