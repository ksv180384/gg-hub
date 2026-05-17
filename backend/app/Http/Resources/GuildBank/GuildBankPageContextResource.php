<?php

namespace App\Http\Resources\GuildBank;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read array{my_permission_slugs: list<string>, dkp_enabled: bool, dkp_ledger_available: bool, my_dkp_balance: int|null} $resource */
class GuildBankPageContextResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'my_permission_slugs' => $this->resource['my_permission_slugs'],
            'dkp_enabled' => $this->resource['dkp_enabled'],
            'dkp_ledger_available' => $this->resource['dkp_ledger_available'],
            'my_dkp_balance' => $this->resource['my_dkp_balance'],
        ];
    }
}
