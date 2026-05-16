<?php

use App\Http\Controllers\Api\GuildDkpController;
use App\Http\Requests\GuildDkp\ListGuildDkpLedgerRequest;
use Domains\GuildDkp\Actions\AdjustGuildUserDkpAction;
use Domains\GuildDkp\Actions\GetGuildUserDkpBalanceAction;
use Domains\GuildDkp\Actions\ListGuildDkpLedgerAction;
use Domains\GuildDkp\Actions\ResolveGuildMemberUserIdAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use function Pest\Laravel\mock;

it('returns 422 for ledger when dkp system is disabled', function () {
    $controller = new GuildDkpController(
        mock(ListGuildDkpLedgerAction::class),
        mock(GetGuildUserDkpBalanceAction::class),
        mock(AdjustGuildUserDkpAction::class),
        mock(ResolveGuildMemberUserIdAction::class),
    );

    $request = ListGuildDkpLedgerRequest::createFrom(Request::create('/dkp/ledger', 'GET'))
        ->setContainer(app())
        ->setRedirector(app('redirect'));

    $response = $controller->ledger($request, makeGuildForDkpUnit(false));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['message'])->toContain('отключена');
});

it('paginates guild dkp ledger entries', function () {
    $entries = [
        new GuildDkpLedgerEntry([
            'id' => 1,
            'amount' => 1,
            'source' => GuildDkpLedgerSource::Manual,
            'balance_after' => 1,
            'occurred_at' => now(),
        ]),
        new GuildDkpLedgerEntry([
            'id' => 2,
            'amount' => 2,
            'source' => GuildDkpLedgerSource::Manual,
            'balance_after' => 3,
            'occurred_at' => now(),
        ]),
    ];
    $paginator = new LengthAwarePaginator($entries, 3, 2, 1);

    $list = mock(ListGuildDkpLedgerAction::class);
    $list->shouldReceive('__invoke')
        ->once()
        ->andReturn($paginator);

    $controller = new GuildDkpController(
        $list,
        mock(GetGuildUserDkpBalanceAction::class),
        mock(AdjustGuildUserDkpAction::class),
        mock(ResolveGuildMemberUserIdAction::class),
    );

    $request = ListGuildDkpLedgerRequest::createFrom(
        Request::create('/dkp/ledger', 'GET', ['per_page' => 2, 'page' => 1])
    )->setContainer(app())->setRedirector(app('redirect'));

    $response = $controller->ledger($request, makeGuildForDkpUnit(true));
    $payload = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['data'])->toHaveCount(2)
        ->and($payload['meta'])->toMatchArray([
            'current_page' => 1,
            'last_page' => 2,
            'per_page' => 2,
            'total' => 3,
        ]);
});
