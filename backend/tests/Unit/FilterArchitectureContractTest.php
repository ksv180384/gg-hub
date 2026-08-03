<?php

use App\Core\Filters\Filter;
use App\Core\Traits\HasFilter;
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
use Domains\Tag\Models\Tag;

it('keeps every application filter on the shared filter contract', function (): void {
    $filterClasses = collect(glob(app_path('Filters/*Filter.php')))
        ->map(fn (string $path): string => 'App\\Filters\\'.pathinfo($path, PATHINFO_FILENAME));

    expect($filterClasses)->not->toBeEmpty();

    foreach ($filterClasses as $filterClass) {
        expect(is_subclass_of($filterClass, Filter::class))->toBeTrue();
    }
});

it('keeps every filtered model on the shared model scope contract', function (string $modelClass): void {
    expect(class_uses_recursive($modelClass))->toContain(HasFilter::class);
})->with([
    GuildActivityLog::class,
    Permission::class,
    Character::class,
    ConstantPartyStorageLog::class,
    Event::class,
    EventHistoryTitle::class,
    Guild::class,
    GuildApplication::class,
    GuildApplicationComment::class,
    GuildAuctionLot::class,
    GuildDkpLedgerEntry::class,
    Poll::class,
    Post::class,
    PostComment::class,
    Tag::class,
]);
