<?php

namespace App\Http\Resources\Landing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LandingCtaClick
 */
class LandingCtaClickResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'button' => $this->button,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
