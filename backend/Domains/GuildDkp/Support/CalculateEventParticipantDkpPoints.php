<?php

namespace Domains\GuildDkp\Support;

final class CalculateEventParticipantDkpPoints
{
    /**
     * @param  array<int, array{character_id?: int|null, dkp_coefficient?: float|int|string|null, dkp_points_override?: int|null}>  $participants
     * @return array<int, int|null>
     */
    public static function resolveAll(?int $basePoints, bool $distributeTotal, array $participants): array
    {
        $amounts = [];
        $guildCoefficients = [];

        foreach ($participants as $index => $participant) {
            $override = $participant['dkp_points_override'] ?? null;
            if ($override !== null) {
                $amounts[$index] = (int) $override;

                continue;
            }

            if ($basePoints === null || empty($participant['character_id'])) {
                $amounts[$index] = null;

                continue;
            }

            $guildCoefficients[$index] = self::normalizeCoefficient($participant['dkp_coefficient'] ?? null);
        }

        $sumCoefficients = $distributeTotal ? array_sum($guildCoefficients) : 0.0;

        foreach ($guildCoefficients as $index => $coefficient) {
            if ($distributeTotal) {
                $amounts[$index] = $sumCoefficients > 0
                    ? (int) round($basePoints * $coefficient / $sumCoefficients)
                    : null;
            } else {
                $amounts[$index] = (int) round($basePoints * $coefficient);
            }
        }

        return $amounts;
    }

    public static function resolve(?int $basePoints, float|int|string|null $coefficient, ?int $override): ?int
    {
        if ($override !== null) {
            return $override;
        }

        if ($basePoints === null) {
            return null;
        }

        return (int) round($basePoints * self::normalizeCoefficient($coefficient));
    }

    private static function normalizeCoefficient(float|int|string|null $coefficient): float
    {
        return is_numeric($coefficient) ? (float) $coefficient : 1.0;
    }
}
