<?php

namespace App\Http\Controllers\Api;

use App\Filters\GuildFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\GuildFilterRequest;
use App\Http\Requests\Guild\StoreGuildRequest;
use App\Http\Requests\Guild\UpdateGuildRequest;
use App\Http\Resources\Guild\GuildApplicationFormResource;
use App\Http\Resources\Guild\GuildResource;
use Domains\Guild\Actions\CreateGuildAction;
use Domains\Guild\Actions\GetGuildAction;
use Domains\Guild\Actions\GetUserGuildPermissionSlugsAction;
use Domains\Guild\Actions\LeaveGuildAction;
use Domains\Guild\Actions\UpdateGuildAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildController extends Controller
{
    public function __construct(
        private CreateGuildAction $createGuildAction,
        private GetGuildAction $getGuildAction,
        private UpdateGuildAction $updateGuildAction,
        private GetUserGuildPermissionSlugsAction $getUserGuildPermissionSlugsAction,
        private LeaveGuildAction $leaveGuildAction
    ) {}

    public function index(GuildFilterRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 15;
        $filter = new GuildFilter($request);
        $guilds = Guild::query()
            ->with(['game', 'localization', 'server', 'leader'])
            ->withCount('members')
            ->filter($filter)
            ->paginate($perPage);

        return GuildResource::collection($guilds);
    }

    public function show(Guild $guild): JsonResponse
    {
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader', 'tags']);
        return response()->json(new GuildResource($guild));
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
        $guild->loadCount('members')->load(['game', 'localization', 'server', 'leader', 'tags', 'applicationFormFields']);
        $data = (new GuildResource($guild))->toArray($request);
        $user = $request->user();
        $data['my_permission_slugs'] = $user ? ($this->getUserGuildPermissionSlugsAction)($user, $guild)->all() : [];

        return response()->json(['data' => $data]);
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
