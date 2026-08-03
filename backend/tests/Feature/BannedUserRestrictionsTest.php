<?php

use Domains\Character\Models\Character;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Post\Models\Post;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->bannedUser = User::factory()->banned()->create();

    $game = Game::query()->create([
        'name' => 'Test Game',
        'slug' => 'test-game',
        'is_active' => true,
    ]);

    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'ru',
        'name' => 'RU',
        'is_active' => true,
    ]);

    $server = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Test Server',
        'slug' => 'test-server',
        'is_active' => true,
    ]);

    $this->character = Character::query()->create([
        'user_id' => $this->user->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Allowed Character',
    ]);

    $this->bannedCharacter = Character::query()->create([
        'user_id' => $this->bannedUser->id,
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'server_id' => $server->id,
        'name' => 'Banned Character',
    ]);
});

it('forbids banned users from creating posts', function () {
    $this->actingAs($this->bannedUser)
        ->postJson('/api/v1/user/posts', [
            'title' => 'Post title',
            'body' => 'Post body',
            'character_id' => $this->bannedCharacter->id,
            'is_visible_global' => false,
            'is_visible_guild' => false,
        ])
        ->assertForbidden()
        ->assertJson(['message' => 'Ваш аккаунт заблокирован. Вы не можете выполнять это действие.']);
});

it('allows non-banned users to create posts', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/user/posts', [
            'title' => 'Post title',
            'body' => 'Post body',
            'character_id' => $this->character->id,
            'is_visible_global' => false,
            'is_visible_guild' => false,
        ])
        ->assertSuccessful();
});

it('forbids banned users from updating posts', function () {
    $post = Post::query()->create([
        'user_id' => $this->bannedUser->id,
        'body' => 'Original body',
        'status_global' => 'published',
        'status_guild' => null,
        'published_at_global' => now(),
    ]);

    $this->actingAs($this->bannedUser)
        ->patchJson("/api/v1/user/posts/{$post->id}", [
            'body' => 'Updated body',
            'is_visible_global' => false,
            'is_visible_guild' => false,
        ])
        ->assertForbidden();
});
