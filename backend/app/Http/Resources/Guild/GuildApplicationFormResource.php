<?php

namespace App\Http\Resources\Guild;

use App\Services\GuildLogoService;
use Domains\Guild\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guild */
class GuildApplicationFormResource extends JsonResource
{
    /**
     * Публичные данные гильдии для страницы подачи заявки (без auth).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logoUrlWithVersion(GuildLogoService::url($this->logo_path)),
            'logo_card_url' => $this->logoUrlWithVersion(GuildLogoService::urlCard($this->logo_path)),
            'is_recruiting' => $this->is_recruiting,
            'application_form_description' => $this->application_form_description,
            'game' => $this->whenLoaded('game', fn () => [
                'id' => $this->game->id,
                'name' => $this->game->name,
            ]),
            'server' => $this->whenLoaded('server', fn () => [
                'id' => $this->server->id,
                'name' => $this->server->name,
            ]),
            'application_form_fields' => GuildApplicationFormFieldResource::collection($this->whenLoaded('applicationFormFields')),
        ];
    }

    private function logoUrlWithVersion(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $ts = $this->updated_at?->timestamp ?? null;
        $separator = str_contains($url, '?') ? '&' : '?';

        return $ts !== null ? $url . $separator . 'v=' . $ts : $url;
    }
}
