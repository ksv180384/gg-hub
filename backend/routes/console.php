<?php

use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sitemap:generate', function () {
    $frontendUrl = (string) config('app.frontend_url', config('app.url'));
    $frontendUrl = rtrim($frontendUrl, '/');
    $now = Carbon::now();

    $sitemap = Sitemap::create()
        ->add(
            Url::create("{$frontendUrl}/")
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                ->setPriority(1.0)
        )
        ->add(
            Url::create("{$frontendUrl}/guilds")
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.9)
        );

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

    $targetPath = public_path('sitemap.xml');
    File::ensureDirectoryExists(dirname($targetPath));
    $sitemap->writeToFile($targetPath);

    $this->info("Sitemap written to: {$targetPath}");
})->purpose('Generate sitemap.xml for frontend');
