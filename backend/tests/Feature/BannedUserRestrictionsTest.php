<?php

use App\Models\User;
use Domains\Post\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->bannedUser = User::factory()->banned()->create();
});

it('forbids banned users from creating posts', function () {
    $this->actingAs($this->bannedUser)
        ->postJson('/api/v1/user/posts', [
            'body' => 'Post body',
            'is_visible_global' => false,
            'is_visible_guild' => false,
        ])
        ->assertForbidden()
        ->assertJson(['message' => 'Ваш аккаунт заблокирован. Вы не можете выполнять это действие.']);
});

it('allows non-banned users to create posts', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/user/posts', [
            'body' => 'Post body',
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
