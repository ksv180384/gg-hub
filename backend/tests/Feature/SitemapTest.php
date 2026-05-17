<?php

use App\Models\User;
use Domains\Game\Models\Game;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('includes global post URL with game subdomain when post has game', function () {
    config(['app.frontend_url' => 'https://gg-hub.test']);

    $author = User::factory()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'tl',
        'description' => null,
        'image' => null,
        'is_active' => true,
    ]);

    $post = Post::create([
        'user_id' => $author->id,
        'character_id' => null,
        'guild_id' => null,
        'game_id' => $game->id,
        'title' => 'In journal',
        'preview' => null,
        'body' => 'Body',
        'is_visible_global' => true,
        'is_visible_guild' => false,
        'is_anonymous' => false,
        'is_global_as_guild' => false,
        'status_global' => PostStatus::Published->value,
        'status_guild' => null,
        'published_at_global' => now(),
        'published_at_guild' => null,
        'published_at' => null,
        'views_count' => 0,
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://tl.gg-hub.test/posts/' . $post->id, false);
});

it('includes games catalog page URL', function () {
    config(['app.frontend_url' => 'https://gg-hub.test']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://gg-hub.test/games', false);
});

it('includes global post URL on base host when post has no game', function () {
    config(['app.frontend_url' => 'https://gg-hub.test']);

    $author = User::factory()->create();

    $post = Post::create([
        'user_id' => $author->id,
        'character_id' => null,
        'guild_id' => null,
        'game_id' => null,
        'title' => 'No game',
        'preview' => null,
        'body' => 'Body',
        'is_visible_global' => true,
        'is_visible_guild' => false,
        'is_anonymous' => false,
        'is_global_as_guild' => false,
        'status_global' => PostStatus::Published->value,
        'status_guild' => null,
        'published_at_global' => now(),
        'published_at_guild' => null,
        'published_at' => null,
        'views_count' => 0,
    ]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('https://gg-hub.test/posts/' . $post->id, false);
});
