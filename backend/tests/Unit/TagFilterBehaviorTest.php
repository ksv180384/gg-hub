<?php

use App\Filters\TagFilter;
use App\Http\Requests\Tag\TagListFilterRequest;
use Domains\Tag\Models\Tag;
use Domains\User\Models\User;

it('applies public tag visibility without querying membership', function (): void {
    $request = TagListFilterRequest::create('/tags', 'GET');
    $request->setUserResolver(fn (): null => null);

    $query = (new TagFilter($request))->apply(Tag::query());
    $sql = strtolower(str_replace(['"', '`'], '', $query->toSql()));

    expect($sql)->toContain('is_hidden = ?')
        ->and($query->getBindings())->toBe([false]);
});

it('applies every administrative tag filter without a database connection', function (): void {
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 1]);
    $user->shouldReceive('getAllPermissionSlugs')->once()->andReturn(['admnistrirovanie']);

    $request = TagListFilterRequest::create('/tags', 'GET', [
        'include_hidden' => '1',
        'kind' => 'guild',
        'tag_name' => 'Raid',
        'guild_name' => 'Knights',
        'user_name' => 'Alex',
    ]);
    $request->setUserResolver(fn (): User => $user);

    $query = (new TagFilter($request))->apply(Tag::query());
    $sql = strtolower(str_replace(['"', '`'], '', $query->toSql()));

    expect($sql)
        ->toContain('used_by_guild_id is not null')
        ->toContain('name like ?')
        ->toContain('used_by_user_id is not null')
        ->not->toContain('is_hidden = ?');
});
