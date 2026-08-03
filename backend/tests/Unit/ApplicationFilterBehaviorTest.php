<?php

use App\Filters\AdminCharacterFilter;
use App\Filters\CharacterFilter;
use App\Filters\ConstantPartyStorageLogFilter;
use App\Filters\EventFilter;
use App\Filters\EventHistoryTitleFilter;
use App\Filters\GuildActivityLogFilter;
use App\Filters\GuildApplicationCommentFilter;
use App\Filters\GuildApplicationFilter;
use App\Filters\GuildAuctionLotFilter;
use App\Filters\GuildDkpLedgerFilter;
use App\Filters\GuildFilter;
use App\Filters\GuildPostFilter;
use App\Filters\PermissionScopeFilter;
use App\Filters\PollFilter;
use App\Filters\PostCommentFilter;
use App\Filters\PostFilter;
use App\GuildActivityLog;
use Domains\Access\Models\Permission;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Domains\Event\Models\Event;
use Domains\Event\Models\EventHistoryTitle;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Domains\Guild\Models\GuildApplicationComment;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Domains\Poll\Models\Poll;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Illuminate\Http\Request;

dataset('application filter behavior', [
    'admin characters' => [
        AdminCharacterFilter::class,
        Character::class,
        ['name' => 'Mage', 'email' => 'mail', 'game_id' => '4', 'server_id' => '8'],
        ['characters.name like ?', 'users.email like ?', 'characters.game_id = ?', 'characters.server_id = ?'],
    ],
    'characters' => [
        CharacterFilter::class,
        Character::class,
        [
            'query' => 'Ma_ge%',
            'game_id' => '4',
            'server_id' => '8',
            'localization_ids' => ['2', '3'],
            'server_ids' => ['8', '9'],
            'game_class_ids' => ['5'],
        ],
        ['name like ?', 'game_id = ?', 'server_id = ?', 'localization_id in (?, ?)', 'server_id in (?, ?)', 'game_class_id = ?'],
    ],
    'constant party storage logs' => [
        ConstantPartyStorageLogFilter::class,
        ConstantPartyStorageLog::class,
        ['date_from' => '2026-08-01', 'date_to' => '2026-08-03'],
        ['created_at', '>= cast(? as text)', '<= cast(? as text)'],
    ],
    'events' => [
        EventFilter::class,
        Event::class,
        ['from' => '2026-08-01', 'to' => '2026-08-31'],
        ['ends_at', '>= cast(? as text)', 'recurrence_ends_at', 'starts_at', '<= cast(? as text)'],
    ],
    'event history titles' => [
        EventHistoryTitleFilter::class,
        EventHistoryTitle::class,
        ['query' => ' Raid '],
        ['lower(name) like ?'],
    ],
    'guild activity logs' => [
        GuildActivityLogFilter::class,
        GuildActivityLog::class,
        [
            'created_from' => '2026-08-01',
            'created_to' => '2026-08-03',
            'category' => 'roulette',
            'action' => 'spin',
            'actor_name' => 'Alex',
            'search' => 'winner',
        ],
        ['created_at >= ?', 'created_at <= ?', 'category = ?', 'action = ?', 'actor_name like ?', 'description like ?'],
    ],
    'guild application comments' => [
        GuildApplicationCommentFilter::class,
        GuildApplicationComment::class,
        ['application_id' => '17'],
        ['guild_application_id = ?'],
    ],
    'guild applications' => [
        GuildApplicationFilter::class,
        GuildApplication::class,
        ['status' => 'pending', 'character_name' => 'Mage'],
        ['status = ?', 'name like ?'],
    ],
    'guild auction lots' => [
        GuildAuctionLotFilter::class,
        GuildAuctionLot::class,
        ['date_from' => '2026-08-01', 'date_to' => '2026-08-03'],
        ['closed_at >= ?', 'closed_at <= ?'],
    ],
    'guild dkp ledger' => [
        GuildDkpLedgerFilter::class,
        GuildDkpLedgerEntry::class,
        [
            'occurred_from' => '2026-08-01',
            'occurred_to' => '2026-08-03',
            'user_name' => 'Alex',
            'event_history_title_id' => '7',
            'source' => 'event',
        ],
        ['occurred_at >= ?', 'occurred_at <= ?', 'name like ?', 'event_history_title_id = ?', 'source = ?'],
    ],
    'guilds' => [
        GuildFilter::class,
        Guild::class,
        [
            'name' => 'Knights',
            'game_id' => '4',
            'localization_id' => '2',
            'server_id' => '8',
            'localization_ids' => ['2', '3'],
            'server_ids' => ['8', '9'],
            'is_recruiting' => '1',
        ],
        ['name like ?', 'game_id = ?', 'localization_id = ?', 'server_id = ?', 'localization_id in (?, ?)', 'server_id in (?, ?)', 'is_recruiting = ?'],
    ],
    'permission scope' => [
        PermissionScopeFilter::class,
        Permission::class,
        ['scope' => 'guild'],
        ['scope = ?'],
    ],
    'polls' => [
        PollFilter::class,
        Poll::class,
        ['guild_id' => '42'],
        ['guild_id = ?'],
    ],
    'post comments' => [
        PostCommentFilter::class,
        PostComment::class,
        ['post_id' => '9'],
        ['post_id = ?'],
    ],
    'posts' => [
        PostFilter::class,
        Post::class,
        ['scope' => 'guild', 'guild_id' => '7', 'game_id' => '4', 'status' => 'published', 'q' => 'Guide'],
        ['guild_id is not null', 'guild_id = ?', 'game_id = ?', 'status_guild = ?', 'title like ?'],
    ],
]);

it('applies every supported list filter without executing a database query', function (
    string $filterClass,
    string $modelClass,
    array $input,
    array $expectedSql,
): void {
    $request = Request::create('/filter-contract', 'GET', $input);
    $query = (new $filterClass($request))->apply($modelClass::query());
    $sql = strtolower(str_replace(['"', '`'], '', $query->toSql()));

    foreach ($expectedSql as $fragment) {
        expect($sql)->toContain(strtolower($fragment));
    }
})->with('application filter behavior');

it('keeps guild post states and their fixed sorting explicit', function (string $state, array $expected): void {
    $request = Request::create('/guild-posts', 'GET', ['filter' => $state]);
    $query = (new GuildPostFilter($request))->apply(Post::query());
    $sql = strtolower(str_replace(['"', '`'], '', $query->toSql()));

    foreach ($expected as $fragment) {
        expect($sql)->toContain($fragment);
    }
})->with([
    'published' => ['', ['status_guild = ?', 'published_at_guild is not null', 'order by published_at_guild desc, created_at desc']],
    'blocked' => ['blocked', ['status_guild = ?', 'order by updated_at desc, created_at desc']],
]);
