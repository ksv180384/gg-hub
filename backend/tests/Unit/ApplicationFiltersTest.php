<?php

use App\Core\Filters\Filter;
use App\Core\Traits\HasFilter;
use App\Filters\AdminCharacterFilter;
use App\Filters\ConstantPartyStorageLogFilter;
use App\Filters\EventFilter;
use App\Filters\EventHistoryTitleFilter;
use App\Filters\GuildApplicationCommentFilter;
use App\Filters\GuildAuctionLotFilter;
use App\Filters\GuildPostFilter;
use App\Filters\PermissionScopeFilter;
use App\Filters\PollFilter;
use App\Filters\PostCommentFilter;
use App\Filters\PostFilter;
use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantPartyStorageLog;
use Domains\Event\Models\Event;
use Domains\Event\Models\EventHistoryTitle;
use Domains\Guild\Models\GuildApplicationComment;
use Domains\GuildAuction\Models\GuildAuctionLot;
use Domains\Poll\Models\Poll;
use Domains\Post\Models\Post;
use Domains\Post\Models\PostComment;
use Illuminate\Http\Request;

it('keeps list filter classes on the shared filter contract', function (string $filterClass): void {
    expect(is_subclass_of($filterClass, Filter::class))->toBeTrue();
})->with([
    AdminCharacterFilter::class,
    ConstantPartyStorageLogFilter::class,
    EventFilter::class,
    EventHistoryTitleFilter::class,
    GuildApplicationCommentFilter::class,
    GuildPostFilter::class,
    GuildAuctionLotFilter::class,
    PermissionScopeFilter::class,
    PollFilter::class,
    PostCommentFilter::class,
    PostFilter::class,
]);

it('keeps filtered models on the HasFilter contract', function (string $modelClass): void {
    expect(class_uses_recursive($modelClass))->toContain(HasFilter::class);
})->with([
    Character::class,
    ConstantPartyStorageLog::class,
    Event::class,
    EventHistoryTitle::class,
    GuildApplicationComment::class,
    GuildAuctionLot::class,
    Permission::class,
    PermissionGroup::class,
    Poll::class,
    Post::class,
    PostComment::class,
]);

it('applies filters from a regular http request', function (): void {
    $request = Request::create('/admin/polls', 'GET', ['guild_id' => '42']);

    $query = (new PollFilter($request))->apply(Poll::query());

    expect(str_replace(['"', '`'], '', $query->toSql()))->toContain('guild_id = ?')
        ->and($query->getBindings())->toBe([42]);
});

it('applies dependent post filters through one filter object', function (): void {
    $request = Request::create('/admin/posts', 'GET', [
        'scope' => 'guild',
        'guild_id' => '7',
        'status' => 'published',
        'game_id' => '3',
    ]);

    $query = (new PostFilter($request))->apply(Post::query());

    expect(str_replace(['"', '`'], '', $query->toSql()))
        ->toContain('guild_id is not null')
        ->toContain('guild_id = ?')
        ->toContain('status_guild = ?')
        ->toContain('game_id = ?')
        ->and($query->getBindings())->toBe([7, 'published', 3]);
});
