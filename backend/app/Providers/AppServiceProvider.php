<?php

namespace App\Providers;

use App\Contracts\Repositories\CharacterRepositoryInterface;
use App\Contracts\Repositories\GameRepositoryInterface;
use App\Contracts\Repositories\GuildRepositoryInterface;
use App\Contracts\Repositories\LocalizationRepositoryInterface;
use App\Observers\GuildActivityObserver;
use App\Observers\NotificationObserver;
use App\Observers\PollObserver;
use App\Repositories\Eloquent\EloquentCharacterRepository;
use App\Repositories\Eloquent\EloquentGameRepository;
use App\Repositories\Eloquent\EloquentGuildRepository;
use App\Repositories\Eloquent\EloquentLocalizationRepository;
use Domains\Access\Models\GuildRole;
use Domains\Event\Models\Event as GuildEvent;
use Domains\Event\Models\EventHistory;
use Domains\Event\Models\EventParticipant;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\GuildAuction\Models\GuildAuctionBid;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildBank\Models\GuildBankItemTier;
use Domains\Notification\Models\Notification;
use Domains\Poll\Models\Poll;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Domains\Tag\Models\Tag;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Yandex\Provider;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim(
                (string) (config('app.frontend_url') ?: config('app.url')),
                '/',
            );
            $email = rawurlencode(
                (string) $notifiable->getEmailForPasswordReset(),
            );

            return $frontendUrl
                .'/reset-password?token='.rawurlencode($token)
                .'&email='.$email;
        });

        Route::bind('tag', function (string $value) {
            return Tag::findOrFail($value);
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('yandex', Provider::class);
            $event->extendSocialite('vkontakte', \SocialiteProviders\VKontakte\Provider::class);
        });

        Notification::observe(NotificationObserver::class);
        Poll::observe(PollObserver::class);

        foreach ([
            Guild::class,
            GuildMember::class,
            GuildRole::class,
            GuildBankItem::class,
            GuildBankItemGrant::class,
            GuildBankItemTier::class,
            GuildAuctionLot::class,
            GuildAuctionBid::class,
            GuildEvent::class,
            EventHistory::class,
            EventParticipant::class,
            Post::class,
            PostComment::class,
        ] as $model) {
            $model::observe(GuildActivityObserver::class);
        }
    }
}
