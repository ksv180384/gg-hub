<?php

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\ApplyBankGrantDkpAction;
use Domains\GuildDkp\Actions\GetGuildUserDkpBalanceAction;
use Domains\GuildDkp\Actions\RecordGuildDkpLedgerEntryAction;
use Domains\GuildDkp\Actions\ResolveGuildMemberUserIdAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use Illuminate\Http\Exceptions\HttpResponseException;
use function Pest\Laravel\mock;

it('requires confirmation when grant would make balance negative', function () {
    $balance = mock(GetGuildUserDkpBalanceAction::class);
    $balance->shouldReceive('__invoke')->once()->andReturn(5);

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldReceive('__invoke')->once()->andReturn(1);

    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $action = new ApplyBankGrantDkpAction($balance, $record, $resolve);

    $preview = $action->preview(makeGuildForDkpUnit(true), makeBankItemForDkpUnit(10), 7);

    expect($preview)->toMatchArray([
        'charged' => 10,
        'requires_confirmation' => true,
        'balance' => 5,
        'balance_after' => -5,
    ]);
});

it('throws when grant would make balance negative without confirmation', function () {
    $balance = mock(GetGuildUserDkpBalanceAction::class);
    $balance->shouldReceive('__invoke')->once()->andReturn(5);

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldReceive('__invoke')->once()->andReturn(1);

    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $action = new ApplyBankGrantDkpAction($balance, $record, $resolve);

    $grant = new GuildBankItemGrant;
    $grant->forceFill([
        'id' => 11,
        'received_by_character_id' => 7,
        'reason' => 'Raid',
        'granted_at' => now(),
    ]);

    expect(fn () => ($action)(
        makeGuildForDkpUnit(true),
        makeBankItemForDkpUnit(10),
        $grant,
        User::factory()->make(),
        false,
    ))->toThrow(HttpResponseException::class);
});

it('charges dkp when negative balance is confirmed', function () {
    $balance = mock(GetGuildUserDkpBalanceAction::class);
    $balance->shouldReceive('__invoke')->once()->andReturn(5);

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldReceive('__invoke')->once()->andReturn(1);

    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldReceive('__invoke')
        ->once()
        ->withArgs(function (Guild $guild, array $data) {
            expect($data['amount'])->toBe(-10)
                ->and($data['source'])->toBe(GuildDkpLedgerSource::BankGrant);

            return true;
        })
        ->andReturn(new GuildDkpLedgerEntry);

    $action = new ApplyBankGrantDkpAction($balance, $record, $resolve);

    $grant = new GuildBankItemGrant;
    $grant->forceFill([
        'id' => 11,
        'received_by_character_id' => 7,
        'reason' => 'Raid',
        'granted_at' => now(),
    ]);

    expect(($action)(
        makeGuildForDkpUnit(true),
        makeBankItemForDkpUnit(10),
        $grant,
        User::factory()->make(),
        true,
    ))->toBe(10);
});

it('does not charge dkp when guild dkp is disabled', function () {
    $balance = mock(GetGuildUserDkpBalanceAction::class);
    $balance->shouldNotReceive('__invoke');

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldNotReceive('__invoke');

    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $action = new ApplyBankGrantDkpAction($balance, $record, $resolve);

    $grant = new GuildBankItemGrant;
    $grant->forceFill([
        'id' => 11,
        'received_by_character_id' => 7,
        'reason' => '',
        'granted_at' => now(),
    ]);

    expect(($action)(
        makeGuildForDkpUnit(false),
        makeBankItemForDkpUnit(10),
        $grant,
        null,
        false,
    ))->toBe(0);
});
