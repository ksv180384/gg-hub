<?php

use App\Filters\GuildDkpLedgerFilter;
use App\Http\Requests\GuildDkp\ListGuildDkpLedgerRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery as m;

it('filters ledger by source', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('where')
        ->once()
        ->with('source', 'manual')
        ->andReturnSelf();

    $request = ListGuildDkpLedgerRequest::createFrom(
        Request::create('/dkp/ledger', 'GET', ['source' => 'manual'])
    )->setContainer(app())->setRedirector(app('redirect'));

    $filter = new GuildDkpLedgerFilter($request);
    $filter->apply($builder);
});

it('ignores empty user name filter', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldNotReceive('whereHas');

    $request = ListGuildDkpLedgerRequest::createFrom(
        Request::create('/dkp/ledger', 'GET', ['user_name' => '   '])
    )->setContainer(app())->setRedirector(app('redirect'));

    $filter = new GuildDkpLedgerFilter($request);
    $filter->apply($builder);
});
