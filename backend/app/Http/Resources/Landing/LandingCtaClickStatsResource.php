<?php

namespace App\Http\Resources\Landing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Агрегированная статистика кликов по CTA на лендинге.
 *
 * @property array{total: int, start_free: int, create_account: int, last_click_at: string|null} $resource
 */
class LandingCtaClickStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'],
            'start_free' => $this->resource['start_free'],
            'create_account' => $this->resource['create_account'],
            'last_click_at' => $this->resource['last_click_at'],
        ];
    }
}
