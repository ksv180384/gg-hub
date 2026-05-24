<?php

namespace Domains\GuildDkp\Enums;

enum GuildDkpLedgerSource: string
{
    case Event = 'event';
    case Manual = 'manual';
    case BankGrant = 'bank_grant';
    case BankGrantRevoke = 'bank_grant_revoke';
    case Auction = 'auction';
}
