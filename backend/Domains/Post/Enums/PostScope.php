<?php

namespace Domains\Post\Enums;

enum PostScope: string
{
    case Guild = 'guild';
    case Game = 'game';
    case Global = 'global';
}
