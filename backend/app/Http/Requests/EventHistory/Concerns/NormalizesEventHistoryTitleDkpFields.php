<?php

namespace App\Http\Requests\EventHistory\Concerns;

trait NormalizesEventHistoryTitleDkpFields
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function normalizeEventHistoryTitleDkpFields(array $validated): array
    {
        $distribute = (bool) ($validated['distribute_dkp_to_participants'] ?? false);
        $validated['distribute_dkp_to_participants'] = $distribute;

        if ($distribute) {
            $validated['dkp_base_points'] = null;
        }

        return $validated;
    }
}
