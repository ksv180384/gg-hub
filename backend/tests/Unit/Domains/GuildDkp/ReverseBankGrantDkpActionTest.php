<?php

use App\Models\User;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildDkp\Actions\RecordGuildDkpLedgerEntryAction;
use Domains\GuildDkp\Actions\ResolveGuildMemberUserIdAction;
use Domains\GuildDkp\Actions\ReverseBankGrantDkpAction;
use Domains\GuildDkp\Enums\GuildDkpLedgerSource;
use Domains\GuildDkp\Models\GuildDkpLedgerEntry;
use function Pest\Laravel\mock;

it('refunds dkp on bank grant revoke when dkp system is disabled', function () {
    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldReceive('__invoke')
        ->once()
        ->withArgs(function (Guild $guild, array $data) {
            expect($guild->dkp_enabled)->toBeFalse()
                ->and($data['amount'])->toBe(10)
                ->and($data['source'])->toBe(GuildDkpLedgerSource::BankGrantRevoke);

            return true;
        })
        ->andReturn(new GuildDkpLedgerEntry);

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldReceive('__invoke')->once()->andReturn(42);

    $action = new ReverseBankGrantDkpAction($record, $resolve);

    ($action)(makeGuildForDkpUnit(false), makeBankGrantForDkpUnit(10), User::factory()->make());
});

it('does not record ledger entry when grant has no dkp charged', function () {
    $record = mock(RecordGuildDkpLedgerEntryAction::class);
    $record->shouldNotReceive('__invoke');

    $resolve = mock(ResolveGuildMemberUserIdAction::class);
    $resolve->shouldNotReceive('__invoke');

    $action = new ReverseBankGrantDkpAction($record, $resolve);

    ($action)(makeGuildForDkpUnit(true), makeBankGrantForDkpUnit(0), null);
});
