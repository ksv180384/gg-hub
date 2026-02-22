<?php

namespace App\Http\Resources\Guild;

use App\Http\Resources\Character\CharacterResource;
use App\Http\Resources\Game\GameResource;
use App\Http\Resources\Game\LocalizationResource;
use App\Http\Resources\Game\ServerResource;
use App\Http\Resources\Tag\TagResource;
use App\Services\GuildLogoService;
use Domains\Guild\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guild */
class GuildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logoUrlWithVersion(GuildLogoService::url($this->logo_path)),
            'logo_card_url' => $this->logoUrlWithVersion(GuildLogoService::urlCard($this->logo_path)),
            'show_roster_to_all' => $this->show_roster_to_all ?? false,
            'about_text' => $this->about_text,
            'charter_text' => $this->charter_text,
            'owner_id' => $this->owner_id,
            'leader_character_id' => $this->leader_character_id,
            'leader' => new CharacterResource($this->whenLoaded('leader')),
            'members_count' => $this->members_count ?? 0,
            'is_recruiting' => $this->is_recruiting,
            'game_id' => $this->game_id,
            'localization_id' => $this->localization_id,
            'server_id' => $this->server_id,
            'game' => new GameResource($this->whenLoaded('game')),
            'localization' => new LocalizationResource($this->whenLoaded('localization')),
            'server' => new ServerResource($this->whenLoaded('server')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'application_form_fields' => GuildApplicationFormFieldResource::collection($this->whenLoaded('applicationFormFields')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Добавляет параметр версии к URL логотипа, чтобы браузер не отдавал кэш после смены картинки.
     */
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
