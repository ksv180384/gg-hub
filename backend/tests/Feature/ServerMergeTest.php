<?php

use App\Actions\Server\MergeServersAction;
use App\Actions\Server\ResumeServerMergeAction;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\Game\Jobs\ProcessServerMerge;
use Domains\Game\Models\Game;
use Domains\Game\Models\Localization;
use Domains\Game\Models\Server;
use Domains\Game\Models\ServerMerge;
use Domains\Guild\Models\Guild;
use Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function seedServerMergeContext(): array
{
    $user = User::factory()->create();
    $game = Game::query()->create([
        'name' => 'Merge Game',
        'slug' => 'merge-game',
        'is_active' => true,
    ]);
    $localization = Localization::query()->create([
        'game_id' => $game->id,
        'code' => 'en',
        'name' => 'English',
        'is_active' => true,
    ]);
    $target = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Target',
        'slug' => 'target',
        'is_active' => true,
    ]);
    $source = Server::query()->create([
        'game_id' => $game->id,
        'localization_id' => $localization->id,
        'name' => 'Source',
        'slug' => 'source',
        'is_active' => true,
    ]);

    return compact('user', 'game', 'localization', 'target', 'source');
}

it('moves large server data in chunks and finalizes server groups', function () {
    config()->set('server_merge.chunk_size', 2);
    $ctx = seedServerMergeContext();

    $characters = collect(range(1, 5))->map(fn (int $number): Character => Character::query()->create([
        'user_id' => $ctx['user']->id,
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'server_id' => $ctx['source']->id,
        'name' => "Character {$number}",
    ]));

    collect(range(1, 3))->each(fn (int $number) => Guild::query()->create([
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'server_id' => $ctx['source']->id,
        'name' => "Guild {$number}",
        'slug' => "guild-{$number}",
        'owner_id' => $ctx['user']->id,
    ]));

    ConstantParty::query()->create([
        'leader_character_id' => $characters->first()->id,
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'server_id' => $ctx['source']->id,
        'created_by_user_id' => $ctx['user']->id,
        'name' => 'Static Party',
    ]);

    $groupId = DB::table('server_groups')->insertGetId([
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'name' => 'Group',
        'slug' => 'group',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('server_group_server')->insert([
        'server_group_id' => $groupId,
        'server_id' => $ctx['source']->id,
    ]);

    $merge = app(MergeServersAction::class)(
        $ctx['game'],
        $ctx['localization'],
        $ctx['target']->id,
        [$ctx['source']->id],
        $ctx['user']->id,
    )->fresh();

    expect($merge->status)->toBe(ServerMerge::STATUS_COMPLETED)
        ->and($merge->processed_records)->toBe($merge->total_records)
        ->and(Character::query()->where('server_id', $ctx['source']->id)->exists())->toBeFalse()
        ->and(Guild::query()->where('server_id', $ctx['source']->id)->exists())->toBeFalse()
        ->and(ConstantParty::query()->where('server_id', $ctx['source']->id)->exists())->toBeFalse()
        ->and(DB::table('server_group_server')
            ->where('server_group_id', $groupId)
            ->where('server_id', $ctx['source']->id)
            ->exists())->toBeFalse()
        ->and(DB::table('server_group_server')
            ->where('server_group_id', $groupId)
            ->where('server_id', $ctx['target']->id)
            ->exists())->toBeTrue()
        ->and($ctx['source']->fresh()->merged_into_server_id)->toBe($ctx['target']->id)
        ->and($ctx['source']->fresh()->is_merging)->toBeFalse()
        ->and($ctx['target']->fresh()->is_merging)->toBeFalse();
});

it('continues from committed chunks after a failed merge is resumed', function () {
    config()->set('queue.default', 'database');
    config()->set('server_merge.chunk_size', 2);
    Queue::fake();

    $ctx = seedServerMergeContext();

    collect(range(1, 5))->each(fn (int $number) => Character::query()->create([
        'user_id' => $ctx['user']->id,
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'server_id' => $ctx['source']->id,
        'name' => "Character {$number}",
    ]));

    $merge = app(MergeServersAction::class)(
        $ctx['game'],
        $ctx['localization'],
        $ctx['target']->id,
        [$ctx['source']->id],
        $ctx['user']->id,
    );

    (new ProcessServerMerge($merge->id))->handle();

    expect(Character::query()->where('server_id', $ctx['target']->id)->count())->toBe(2);

    (new ProcessServerMerge($merge->id))->failed(new RuntimeException('Temporary database error'));
    $failedMerge = $merge->fresh();

    expect($failedMerge->status)->toBe(ServerMerge::STATUS_FAILED)
        ->and($ctx['source']->fresh()->is_merging)->toBeTrue();

    expect(fn () => Character::query()->create([
        'user_id' => $ctx['user']->id,
        'game_id' => $ctx['game']->id,
        'localization_id' => $ctx['localization']->id,
        'server_id' => $ctx['source']->id,
        'name' => 'Blocked Character',
    ]))->toThrow(ValidationException::class);

    app(ResumeServerMergeAction::class)($failedMerge);

    for ($step = 0; $step < 10; $step++) {
        $merge->refresh();

        if ($merge->status === ServerMerge::STATUS_COMPLETED) {
            break;
        }

        (new ProcessServerMerge($merge->id))->handle();
    }

    $merge->refresh();

    expect($merge->status)->toBe(ServerMerge::STATUS_COMPLETED)
        ->and(Character::query()->where('server_id', $ctx['target']->id)->count())->toBe(5)
        ->and(Character::query()->where('server_id', $ctx['source']->id)->exists())->toBeFalse()
        ->and($ctx['source']->fresh()->merged_into_server_id)->toBe($ctx['target']->id);
});
