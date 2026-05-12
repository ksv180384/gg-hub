<?php

namespace App\Http\Resources\GuildDkp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{user_id:int,balance:int} $resource */
class GuildUserDkpBalanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->resource['user_id'],
            'balance' => $this->resource['balance'],
        ];
    }
}
