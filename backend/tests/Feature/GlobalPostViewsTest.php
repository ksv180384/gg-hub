<?php

use App\Models\User;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostView;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('records a global post view once per session', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $post = Post::create([
        'user_id' => $author->id,
        'character_id' => null,
        'guild_id' => null,
        'game_id' => null,
        'title' => 'Test post',
        'preview' => null,
        'body' => 'Test body',
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

    $this->actingAs($viewer);

    $first = $this->getJson("/api/v1/posts/{$post->id}")
        ->assertSuccessful()
        ->json();

    $firstViewsCount = data_get($first, 'data.views_count', data_get($first, 'views_count'));
    expect($firstViewsCount)->toBe(1);

    $second = $this->getJson("/api/v1/posts/{$post->id}")
        ->assertSuccessful()
        ->json();

    $secondViewsCount = data_get($second, 'data.views_count', data_get($second, 'views_count'));
    expect($secondViewsCount)->toBe(1);

    expect(PostView::where('post_id', $post->id)->count())->toBe(1);
    expect(Post::query()->findOrFail($post->id)->views_count)->toBe(1);
});

