<?php

namespace Domains\Guild\Enums;

enum GuildApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
