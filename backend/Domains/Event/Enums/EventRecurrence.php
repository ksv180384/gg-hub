<?php

namespace Domains\Event\Enums;

/**
 * Повторение эвента в календаре.
 */
enum EventRecurrence: string
{
    case Once = 'once';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
