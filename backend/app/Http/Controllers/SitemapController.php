<?php

namespace App\Http\Controllers;

use Domains\Guild\Models\Guild;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $frontendUrl = $this->frontendBaseUrl();
        $now = Carbon::now();

        $sitemap = Sitemap::create();
        $this->addStaticUrls($sitemap, $frontendUrl, $now);
        $this->addGuildUrls($sitemap, $frontendUrl);
        $this->addGlobalPostUrls($sitemap, $frontendUrl);

        return $this->xmlResponse($sitemap);
    }

    private function frontendBaseUrl(): string
    {
        $frontendUrl = (string) config('app.frontend_url', config('app.url'));
        return rtrim($frontendUrl, '/');
    }

    private function addStaticUrls(Sitemap $sitemap, string $frontendUrl, Carbon $now): void
    {
        $sitemap
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
    }

    private function addGuildUrls(Sitemap $sitemap, string $frontendUrl): void
    {
        Guild::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($guilds) use ($sitemap, $frontendUrl) {
                foreach ($guilds as $guild) {
                    $lastmod = $guild->updated_at ? Carbon::parse($guild->updated_at) : null;

                    $sitemap->add(
                        Url::create("{$frontendUrl}/guilds/{$guild->id}/info")
                            ->setLastModificationDate($lastmod)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7)
                    );
                }
            }, 'id');
    }

    private function addGlobalPostUrls(Sitemap $sitemap, string $frontendUrl): void
    {
        Post::query()
            ->where('is_visible_global', true)
            ->where('status_global', PostStatus::Published->value)
            ->whereNotNull('published_at_global')
            ->select(['id', 'published_at_global', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($posts) use ($sitemap, $frontendUrl) {
                foreach ($posts as $post) {
                    $lastmod = $post->published_at_global ?? $post->updated_at;
                    $lastmod = $lastmod ? Carbon::parse($lastmod) : null;

                    $sitemap->add(
                        Url::create("{$frontendUrl}/posts/{$post->id}")
                            ->setLastModificationDate($lastmod)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.6)
                    );
                }
            }, 'id');
    }

    private function xmlResponse(Sitemap $sitemap): Response
    {
        return ResponseFacade::make($sitemap->render(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}

