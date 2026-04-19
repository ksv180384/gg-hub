<?php

namespace App\Http\Requests\Guild;

use Domains\Character\Models\Character;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Domains\Tag\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Validator;

class UpdateGuildRequest extends FormRequest
{
    /**
     * Пропускает текущего лидера гильдии (владелец персонажа leader_character_id)
     * или пользователя с хотя бы одним slug'ом на редактирование гильдии.
     * Создатель гильдии (owner_id) не имеет особых прав — после смены лидера
     * бывший лидер теряет доступ к настройкам. Конкретные поля проверяет
     * {@see \Domains\Guild\Actions\UpdateGuildAction}.
     */
    public function authorize(): bool
    {
        $guild = $this->route('guild');
        $user = $this->user();
        if (!$guild || !$user) {
            return false;
        }
        if ($guild->leader_character_id) {
            $leaderCharacter = Character::query()->find($guild->leader_character_id);
            if ($leaderCharacter && (int) $leaderCharacter->user_id === (int) $user->id) {
                return true;
            }
        }
        $getSlugs = App::make(GetUserGuildPermissionSlugsAction::class);
        $slugs = $getSlugs($user, $guild);
        $editSlugs = [
            'redaktirovanie-dannyx-gildii',
            'redaktirovanie-opisanie-gildii',
            'redaktirovanie-ustav-gildii',
            'redaktirovat-formu-zaiavki-v-giliudiiu',
            'izmeniat-tegi-gildii',
        ];

        return $slugs->contains(fn (string $slug): bool => in_array($slug, $editSlugs, true));
    }

    /**
     * Возвращает человекочитаемое сообщение вместо дефолтного «This action is unauthorized.».
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Недостаточно прав для изменения этой гильдии.',
            ], 403)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'localization_id' => ['sometimes', 'required', 'integer', 'exists:localizations,id'],
            'server_id' => ['sometimes', 'required', 'integer', 'exists:servers,id'],
            'show_roster_to_all' => ['sometimes', 'boolean'],
            'is_recruiting' => ['sometimes', 'boolean'],
            'about_text' => ['nullable', 'string'],
            'charter_text' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'remove_logo' => ['nullable', 'boolean'],
            'leader_character_id' => ['sometimes', 'required', 'integer', 'exists:characters,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название гильдии.',
            'name.max' => 'Название гильдии не должно превышать 255 символов.',
            'localization_id.required' => 'Выберите локализацию.',
            'localization_id.exists' => 'Выбранная локализация не найдена.',
            'server_id.required' => 'Выберите сервер.',
            'server_id.exists' => 'Выбранный сервер не найден.',
            'logo.image' => 'Логотип должен быть изображением (jpeg, png, gif и т.д.).',
            'logo.max' => 'Размер файла логотипа не должен превышать 5 МБ.',
            'leader_character_id.required' => 'Укажите лидера гильдии (персонажа на этом сервере).',
            'leader_character_id.exists' => 'Выбранный персонаж не найден.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var \Domains\Guild\Models\Guild $guild */
            $guild = $this->route('guild');

            $this->validateAssignableTagIds($validator, $guild);

            if ($this->has('server_id') || $this->has('localization_id')) {
                $membersCount = GuildMember::query()->where('guild_id', $guild->id)->count();
                $isOnlyLeader = $membersCount === 1
                    && $guild->leader_character_id
                    && GuildMember::query()
                        ->where('guild_id', $guild->id)
                        ->where('character_id', $guild->leader_character_id)
                        ->exists();
                if (!$isOnlyLeader) {
                    $message = 'Изменить локализацию или сервер можно только пока в гильдии один участник — Лидер гильдии.';
                    if ($this->has('server_id')) {
                        $validator->errors()->add('server_id', $message);
                    }
                    if ($this->has('localization_id')) {
                        $validator->errors()->add('localization_id', $message);
                    }
                }
            }

            if (!$this->has('leader_character_id')) {
                return;
            }
            /** @var \Domains\Guild\Models\Guild $guild */
            $guild = $this->route('guild');
            $serverId = (int) ($this->input('server_id') ?? $guild->server_id);
            $leaderId = (int) $this->input('leader_character_id');
            if (!$leaderId) {
                return;
            }
            $character = Character::query()->find($leaderId);
            if (!$character) {
                return;
            }
            if (! GuildMember::query()->where('guild_id', $guild->id)->where('character_id', $leaderId)->exists()) {
                $validator->errors()->add('leader_character_id', 'Лидером может быть только участник этой гильдии.');

                return;
            }
            if ((int) $character->server_id !== $serverId) {
                // Исключение: лидер не меняется (тот же персонаж), при этом меняется сервер гильдии —
                // персонаж-лидер будет синхронно перенесён на новый сервер в UpdateGuildAction.
                $isSameLeader = (int) $guild->leader_character_id === $leaderId;
                $serverIsChanging = $this->has('server_id');
                if (!($isSameLeader && $serverIsChanging)) {
                    $validator->errors()->add('leader_character_id', 'Персонаж должен находиться на том же сервере, что и гильдия.');

                    return;
                }
            }
            if (Guild::query()->where('leader_character_id', $leaderId)->where('id', '!=', $guild->id)->exists()) {
                $validator->errors()->add('leader_character_id', 'Этот персонаж уже является лидером другой гильдии.');
            }
        });
    }

    /**
     * Привязывать к гильдии можно только общие теги (обе ссылки NULL)
     * и теги этой же гильдии (`used_by_guild_id = $guild->id`).
     * Личные теги (`used_by_user_id IS NOT NULL`) и теги других гильдий отклоняются.
     *
     * Проверяются только «новые» идентификаторы — те, которых ещё нет в `guild->tags`.
     * Уже привязанные легаси-записи (если остались с прошлых версий) не блокируют сохранение,
     * их снимет {@see \Domains\Guild\Actions\UpdateGuildAction} на следующем `sync()`.
     */
    private function validateAssignableTagIds(Validator $validator, Guild $guild): void
    {
        if (!$this->has('tag_ids')) {
            return;
        }
        $raw = $this->input('tag_ids');
        if (!is_array($raw) || $raw === []) {
            return;
        }
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $raw))));
        if ($tagIds === []) {
            return;
        }
        $currentIds = $guild->tags()->pluck('tags.id')->map(fn ($v) => (int) $v)->all();
        $newIds = array_values(array_diff($tagIds, $currentIds));
        if ($newIds === []) {
            return;
        }
        $invalidIds = Tag::query()
            ->whereIn('id', $newIds)
            ->where(function ($q) use ($guild) {
                $q->whereNotNull('used_by_user_id')
                    ->orWhere(function ($q2) use ($guild) {
                        $q2->whereNotNull('used_by_guild_id')
                            ->where('used_by_guild_id', '!=', $guild->id);
                    });
            })
            ->pluck('id')
            ->all();
        if ($invalidIds === []) {
            return;
        }
        $validator->errors()->add(
            'tag_ids',
            'К гильдии можно привязывать только общие теги или теги этой гильдии. Уберите личные теги и теги других гильдий.'
        );
        foreach ($invalidIds as $id) {
            $validator->errors()->add('tag_ids.' . array_search($id, $tagIds, true), 'Этот тег нельзя привязать к этой гильдии.');
        }
    }
}
