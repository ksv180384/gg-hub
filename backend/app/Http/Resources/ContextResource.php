<?php

namespace App\Http\Resources;

use App\Http\Resources\Game\GameResource;
use Domains\Game\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ресурс ответа GET /context: mode, subdomain, game.
 *
 * @param  array{mode: string, subdomain: string|null, game: Game|null}  $resource
 */
class ContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{mode: string, subdomain: string|null, game: Game|null} $context */
        $context = $this->resource;

        return [
            'mode' => $context['mode'],
            'subdomain' => $context['subdomain'],
            'game' => isset($context['game']) && $context['game'] !== null
                ? new GameResource($context['game'])
                : null,
        ];
    }
}
