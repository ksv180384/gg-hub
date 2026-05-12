<?php

namespace Domains\GuildDkp\Support;

final class CalculateEventParticipantDkpPoints
{
    public static function resolve(?int $basePoints, float|int|string|null $coefficient, ?int $override): ?int
    {
        if ($override !== null) {
            return $override;
        }

        if ($basePoints === null) {
            return null;
        }

        $coef = is_numeric($coefficient) ? (float) $coefficient : 1.0;

        return (int) round($basePoints * $coef);
    }
}
