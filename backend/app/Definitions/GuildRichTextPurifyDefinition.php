<?php

namespace App\Definitions;

use HTMLPurifier_HTMLDefinition;
use Stevebauman\Purify\Definitions\Definition;
use Stevebauman\Purify\Definitions\Html5Definition;

/**
 * Определение для HTMLPurifier: HTML5 + атрибуты data-width, data-align, data-wrap у img
 * (для контента «О гильдии» и «Устав»).
 */
class GuildRichTextPurifyDefinition implements Definition
{
    public static function apply(HTMLPurifier_HTMLDefinition $definition): void
    {
        Html5Definition::apply($definition);

        $definition->addAttribute('img', 'data-width', 'Text');
        $definition->addAttribute('img', 'data-align', 'Text');
        $definition->addAttribute('img', 'data-wrap', 'Text');
    }
}
