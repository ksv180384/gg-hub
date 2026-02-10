<?php

namespace Domains\Access\Enums;

enum PermissionScope: string
{
    case Site = 'site';
    case Guild = 'guild';
}
