<?php

namespace App\Http\Controllers\Api;

use App\Filters\GuildFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\GuildFilterRequest;
use App\Http\Requests\Guild\StoreGuildRequest;
use App\Http\Requests\Guild\StoreGuildTagRequest;
use App\Http\Requests\Guild\UpdateGuildMemberRoleRequest;
use App\Http\Requests\Guild\UpdateGuildRosterMemberTagsRequest;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Http\Resources\Guild\GuildApplicationFormResource;
use App\Http\Resources\Guild\GuildResource;
use App\Http\Resources\Guild\GuildRosterMemberResource;
use App\Http\Resources\Tag\TagResource;
use Domains\Guild\Actions\CreateGuildAction;
use Domains\Tag\Actions\CreateTagAction;
use Domains\Tag\Actions\DeleteTagAction;
use Domains\Tag\Models\Tag;
use Domains\Guild\Actions\ExcludeGuildMemberAction;
use Domains\Guild\Actions\GetGuildAction;
use Domains\Guild\Actions\GetGuildRosterAction;
use Domains\Guild\Actions\GetGuildRosterMemberAction;
use Domains\Guild\Actions\GetUserGuildCharactersAction;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Actions\LeaveGuildAction;
use Domains\Guild\Actions\UpdateGuildAction;
use Domains\Guild\Actions\SyncGuildRosterMemberTagsAction;
use Domains\Character\Models\Character;
use Domains\Guild\Actions\UpdateGuildMemberRoleAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildController extends Controller
{
    public function __construct(
        private CreateGuildAction $createGuildAction,
        private ExcludeGuildMemberAction $excludeGuildMemberAction,
        private GetGuildAction $getGuildAction,
        private GetGuildRosterAction $getGuildRosterAction,
        private GetGuildRosterMemberAction $getGuildRosterMemberAction,
        private GetUserGuildCharactersAction $getUserGuildCharactersAction,
        private UpdateGuildAction $updateGuildAction,
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
        private LeaveGuildAction $leaveGuildAction,
        private UpdateGuildMemberRoleAction $updateGuildMemberRoleAction,
        private SyncGuildRosterMemberTagsAction $syncGuildRosterMemberTagsAction,
        private CreateTagAction $createTagAction,
        private DeleteTagAction $deleteTagAction
    ) {}

    public function index(GuildFilterRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;
        $filter = new GuildFilter($request);
        $guilds = Guild::query()
            ->with([
                'game',
                'localization',
                'server',
                'leader',
                'tags' => fn ($q) => $q->notHidden(),
            ])
            ->withCount('members')
            ->filter($filter)
            ->paginate($perPage);

        return GuildResource::collection($guilds);
    }

    public function show(Guild $guild): JsonResponse
    {
        $guild->loadCount('members')->load([
            'game',
            'localization',
            'server',
            'leader',
            'tags' => fn ($q) => $q->notHidden(),
        ]);
        return response()->json(new GuildResource($guild));
    }

    /**
     * Состав гильдии. Доступ: при show_roster_to_all — всем авторизованным; иначе только участникам.
     */
    public function roster(Request $request, Guild $guild): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $result = ($this->getGuildRosterAction)($user, $guild);

        return $result instanceof JsonResponse ? $result : $result;
    }

    /**
     * Один участник состава гильдии (для страницы просмотра). Доступ: как у состава.
     * В ответе can_exclude: можно ли исключить (есть право и это не лидер).
     */
    public function showRosterMember(Request $request, Guild $guild, int $character): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        $result = ($this->getGuildRosterMemberAction)($user, $guild, $character);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $permissionSlugs = ($this->getUserGuildPermissionSlugsAction)($user, $guild);
        $isLeader = $guild->leader_character_id && (int) $guild->leader_character_id === (int) $character;
        $canExclude = $permissionSlugs->contains('iskliucenie-polzovatelia-iz-gildii') && ! $isLeader;
        $canChangeRole = $permissionSlugs->contains('meniat-izieniat-polzovateliu-rol') && ! $isLeader;
        $canEditGuildTags = $permissionSlugs->contains('izmeniat-tegi-polzovatelei-gildii');
        $canCreateGuildTag = $permissionSlugs->contains('dobavliat-teg-gildii');
        $canDeleteGuildTag = $permissionSlugs->contains('udaliat-teg-gildii');

        return response()->json([
            'data' => $result->toArray($request),
            'can_exclude' => $canExclude,
            'can_change_role' => $canChangeRole,
            'can_edit_guild_tags' => $canEditGuildTags,
            'can_create_guild_tag' => $canCreateGuildTag,
            'can_delete_guild_tag' => $canDeleteGuildTag,
        ]);
    }

    /**
     * Создать тег гильдии (used_by_guild_id, без used_by_user_id). Право dobavliat-teg-gildii.
     */
    public function storeTag(StoreGuildTagRequest $request, Guild $guild): JsonResponse
    {
        $data = $request->validated();
        $tag = ($this->createTagAction)([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'used_by_user_id' => null,
            'used_by_guild_id' => $guild->id,
            'created_by_user_id' => $request->user()?->id,
        ]);
        $tag->load(['usedByUser', 'createdByUser', 'usedByGuild']);

        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    /**
     * Удалить тег гильдии (used_by_guild_id = эта гильдия). Право udaliat-teg-gildii.
     */
    public function destroyTag(Guild $guild, Tag $tag): JsonResponse|Response
    {
        if ($tag->used_by_guild_id === null || (int) $tag->used_by_guild_id !== (int) $guild->id) {
            return response()->json(['message' => 'Этот тег не относится к данной гильдии.'], 403);
        }

        ($this->deleteTagAction)($tag);

        return response()->noContent();
    }

    /**
     * Изменить роль участника гильдии. Требуется право meniat-izieniat-polzovateliu-rol.
     */
    public function updateMemberRole(UpdateGuildMemberRoleRequest $request, Guild $guild, int $character): JsonResponse
    {
        ($this->updateGuildMemberRoleAction)($guild, $character, (int) $request->validated()['guild_role_id']);

        return response()->json(['message' => 'Роль участника обновлена.']);
    }

    /**
     * Теги участника в контексте гильдии (character_guild_tag). Право izmeniat-tegi-polzovatelei-gildii.
     */
    public function updateMemberTags(UpdateGuildRosterMemberTagsRequest $request, Guild $guild, int $character): JsonResponse
    {
        $tagIds = $request->validated()['tag_ids'];
        ($this->syncGuildRosterMemberTagsAction)($guild, $character, $tagIds);

        return response()->json(['message' => 'Теги участника обновлены.']);
    }

    /**
     * Исключить участника из гильдии. Требуется право iskliucenie-polzovatelia-iz-gildii.
     * Всем участникам и исключённому пользователю отправляются оповещения.
     */
    public function excludeMember(Request $request, Guild $guild, int $character): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }

        ($this->excludeGuildMemberAction)($user, $guild, $character);

        return response()->json(['message' => 'Участник исключён из гильдии.']);
    }

    /**
     * Публичные данные для страницы подачи заявки в гильдию (форма с полями).
     * Без авторизации. Возвращает название, лого, флаг набора и поля формы.
     */
    public function applicationForm(Guild $guild): JsonResponse
    {
        $guild->load(['game', 'server', 'applicationFormFields']);
        return response()->json(new GuildApplicationFormResource($guild));
    }

    /**
     * Данные гильдии для страницы настроек. Доступно только участникам гильдии.
     * При отсутствии членства возвращается 403 — данные не отдаются.
     * В ответ добавлено my_permission_slugs — права текущего пользователя в этой гильдии.
     */
    public function settings(Request $request, Guild $guild): JsonResponse
    {
        $guild->loadCount('members')->load([
            'game',
            'localization',
            'server',
            'leader',
            'tags' => fn ($q) => $q->notHidden(),
            'applicationFormFields',
        ]);
        $data = (new GuildResource($guild))->toArray($request);
        $user = $request->user();
        $data['my_permission_slugs'] = $user ? ($this->getUserGuildPermissionSlugsAction)($user, $guild)->all() : [];
        $data['my_characters'] = $user
            ? ($this->getUserGuildCharactersAction)($user, $guild)
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'avatar_url' => $c->resolved_avatar_url])
                ->values()
                ->all()
            : [];

        // Сменить лидера может только текущий лидер гильдии (владелец leader_character_id).
        // Создатель гильдии (owner_id) без статуса лидера особых прав не имеет.
        $canChangeGuildLeader = false;
        if ($user && $guild->leader_character_id) {
            $leaderCharacter = Character::query()->find($guild->leader_character_id);
            if ($leaderCharacter && (int) $leaderCharacter->user_id === (int) $user->id) {
                $canChangeGuildLeader = true;
            }
        }
        $data['can_change_guild_leader'] = $canChangeGuildLeader;

        $data['can_change_localization_server'] = $this->computeCanChangeLocalizationServer($guild);

        return response()->json(['data' => $data]);
    }

    /**
     * Сменить локализацию/сервер гильдии можно только когда в гильдии
     * ровно один участник — и он же является лидером гильдии.
     */
    private function computeCanChangeLocalizationServer(Guild $guild): bool
    {
        if (!$guild->leader_character_id) {
            return false;
        }
        $membersCount = (int) ($guild->members_count ?? $guild->members()->count());
        if ($membersCount !== 1) {
            return false;
        }
        $onlyMember = GuildMember::query()->where('guild_id', $guild->id)->first();

        return $onlyMember !== null
            && (int) $onlyMember->character_id === (int) $guild->leader_character_id;
    }

    public function store(StoreGuildRequest $request): JsonResponse
    {
        $guild = ($this->createGuildAction)($request->user(), $request->validated());
        return (new GuildResource($guild))->response()->setStatusCode(201);
    }

    public function update(UpdateGuildRequest $request, Guild $guild): JsonResponse
    {
        $guild = ($this->updateGuildAction)($guild, $request);
        return response()->json(new GuildResource($guild));
    }

    /**
     * Покинуть гильдию (для любого участника, кроме лидера).
     */
    public function leave(Request $request, Guild $guild): JsonResponse
    {
        ($this->leaveGuildAction)($request->user(), $guild);

        return response()->json(['message' => 'Вы покинули гильдию.']);
    }
}
