<?php

namespace App\Http\Resources\EventHistory;

use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventHistoryTitle */
class EventHistoryTitleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dkp_base_points' => $this->dkp_base_points === null ? null : (int) $this->dkp_base_points,
            'distribute_dkp_to_participants' => (bool) ($this->distribute_dkp_to_participants ?? false),
            'histories_count' => $this->when(isset($this->histories_count), fn () => (int) $this->histories_count),
        ];
    }
}
