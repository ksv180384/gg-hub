<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\SocialAuthController;
use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

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

Route::get('/sitemap.xml', function () {
    $frontendUrl = (string) config('app.frontend_url', config('app.url'));
    $frontendUrl = rtrim($frontendUrl, '/');
    $now = Carbon::now();

    $sitemap = Sitemap::create()
        ->add(
            Url::create("{$frontendUrl}/")
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        )
        ->add(
            Url::create("{$frontendUrl}/guilds")
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.9)
        );

    Guild::query()
        ->select(['id', 'updated_at'])
        ->orderBy('id')
        ->chunkById(500, function ($guilds) use (&$sitemap, $frontendUrl) {
            foreach ($guilds as $guild) {
                $lastmod = $guild->updated_at ? Carbon::parse($guild->updated_at) : null;

                // Публичная карточка гильдии
                $sitemap->add(
                    Url::create("{$frontendUrl}/guilds/{$guild->id}/info")
                        ->setLastModificationDate($lastmod)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7)
                );
            }
        }, 'id');

    Post::query()
        ->where('is_visible_global', true)
        ->where('status_global', PostStatus::Published->value)
        ->whereNotNull('published_at_global')
        ->whereNotNull('guild_id')
        ->select(['id', 'guild_id', 'published_at_global', 'updated_at'])
        ->orderBy('id')
        ->chunkById(500, function ($posts) use (&$sitemap, $frontendUrl) {
            foreach ($posts as $post) {
                $lastmod = $post->published_at_global ?? $post->updated_at;
                $lastmod = $lastmod ? Carbon::parse($lastmod) : null;

                $sitemap->add(
                    Url::create("{$frontendUrl}/guilds/{$post->guild_id}/posts/{$post->id}")
                        ->setLastModificationDate($lastmod)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.6)
                );
            }
        }, 'id');

    return Response::make($sitemap->render(), 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=600',
    ]);
});
