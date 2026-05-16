<?php

use App\Models\User;
use Domains\GuildDkp\Actions\AdjustGuildUserDkpAction;
use Domains\GuildDkp\Actions\RecordGuildDkpLedgerEntryAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Http\Exceptions\HttpResponseException;
use function Pest\Laravel\mock;

it('rejects manual adjust when dkp system is disabled', function () {
    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $action = new AdjustGuildUserDkpAction($record);

    expect(fn () => ($action)(
        makeGuildForDkpUnit(false),
        1,
        User::factory()->make(),
        ['amount' => 10],
    ))->toThrow(HttpResponseException::class);
});

it('rejects zero manual adjust amount', function () {
    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $action = new AdjustGuildUserDkpAction($record);

    expect(fn () => ($action)(
        makeGuildForDkpUnit(true),
        1,
        User::factory()->make(),
        ['amount' => 0],
    ))->toThrow(HttpResponseException::class);
});

it('records manual adjust in ledger', function () {
    $entry = new GuildDkpLedgerEntry;
    $entry->forceFill(['amount' => 15, 'source' => GuildDkpLedgerSource::Manual]);

    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldReceive('__invoke')
        ->once()
        ->withArgs(function ($guild, array $data) {
            expect($data['amount'])->toBe(15)
                ->and($data['source'])->toBe(GuildDkpLedgerSource::Manual)
                ->and($data['reason'])->toBe('Bonus');

            return true;
        })
        ->andReturn($entry);

    $action = new AdjustGuildUserDkpAction($record);

    $result = ($action)(
        makeGuildForDkpUnit(true),
        1,
        User::factory()->make(['id' => 5]),
        ['amount' => 15, 'reason' => 'Bonus', 'character_id' => 7],
    );

    expect($result->amount)->toBe(15);
});
