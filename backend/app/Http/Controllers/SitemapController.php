<?php

namespace App\Http\Controllers;

use App\Services\Notifications\GuildLinkBuilder;
use Domains\Game\Models\Game;
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
    public function __construct(
        private readonly GuildLinkBuilder $guildLinkBuilder,
    ) {}

    public function __invoke(): Response
    {
        $frontendUrl = $this->frontendBaseUrl();
        $now = Carbon::now();

        $sitemap = Sitemap::create();
        $this->addStaticUrls($sitemap, $frontendUrl, $now);
        $this->addGameHomeUrls($sitemap, $frontendUrl);
        $this->addGuildUrls($sitemap, $frontendUrl);
        $this->addGlobalPostUrls($sitemap);

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
            )
            ->add(
                Url::create("{$frontendUrl}/games")
                    ->setLastModificationDate($now)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority(0.9)
            );
    }

    private function addGameHomeUrls(Sitemap $sitemap, string $frontendUrl): void
    {
        Game::query()
            ->where('is_active', true)
            ->whereNotNull('slug')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($games) use ($sitemap, $frontendUrl) {
                foreach ($games as $game) {
                    $lastmod = $game->updated_at ? Carbon::parse($game->updated_at) : null;

                    $sitemap->add(
                        Url::create($this->frontendUrlForGame($frontendUrl, (string) $game->slug) . '/')
                            ->setLastModificationDate($lastmod)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                            ->setPriority(0.9)
                    );
                }
            }, 'id');
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

    private function addGlobalPostUrls(Sitemap $sitemap): void
    {
        Post::query()
            ->where('posts.is_visible_global', true)
            ->where('posts.status_global', PostStatus::Published->value)
            ->whereNotNull('posts.published_at_global')
            ->leftJoin('games', 'posts.game_id', '=', 'games.id')
            ->select([
                'posts.id',
                'posts.published_at_global',
                'posts.updated_at',
                'games.slug as game_slug',
            ])
            ->orderBy('posts.id')
            ->chunkById(500, function ($posts) use ($sitemap) {
                foreach ($posts as $post) {
                    $lastmod = $post->published_at_global ?? $post->updated_at;
                    $lastmod = $lastmod ? Carbon::parse($lastmod) : null;

                    $slug = $post->game_slug !== null ? (string) $post->game_slug : null;
                    $url = $this->guildLinkBuilder->globalJournalPostUrl($slug, (int) $post->id);

                    $sitemap->add(
                        Url::create($url)
                            ->setLastModificationDate($lastmod)
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.6)
                    );
                }
            }, 'posts.id', 'id');
    }

    private function frontendUrlForGame(string $frontendUrl, string $gameSlug): string
    {
        $parsed = parse_url($frontendUrl) ?: [];
        $scheme = ($parsed['scheme'] ?? 'https') . '://';
        $host = $parsed['host'] ?? 'gg-hub.ru';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return $scheme . $gameSlug . '.' . $host . $port;
    }

    private function xmlResponse(Sitemap $sitemap): Response
    {
        return ResponseFacade::make($sitemap->render(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
