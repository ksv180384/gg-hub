<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\SetRaidCompositionRequest;
use App\Http\Requests\Raid\StoreRaidRequest;
use App\Http\Requests\Raid\SubmitRaidApplicationRequest;
use App\Http\Requests\Raid\UpdateRaidRecruitmentRequest;
use App\Http\Requests\Raid\UpdateRaidRequest;
use App\Http\Resources\Raid\RaidApplicationResource;
use App\Http\Resources\Raid\RaidResource;
use Domains\Guild\Models\Guild;
use Domains\Raid\Actions\CanManageRaidRecruitmentAction;
use Domains\Raid\Actions\CreateRaidAction;
use Domains\Raid\Actions\DecideRaidApplicationAction;
use Domains\Raid\Actions\DeleteRaidAction;
use Domains\Raid\Actions\GetRaidAction;
use Domains\Raid\Actions\ListGuildRaidsAction;
use Domains\Raid\Actions\ListRaidDescendantUsersAction;
use Domains\Raid\Actions\SetRaidCompositionAction;
use Domains\Raid\Actions\SubmitRaidApplicationAction;
use Domains\Raid\Actions\UpdateRaidAction;
use Domains\Raid\Models\RaidApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RaidController extends Controller
{
    public function __construct(
        private ListGuildRaidsAction $listGuildRaidsAction,
        private GetRaidAction $getRaidAction,
        private CreateRaidAction $createRaidAction,
        private UpdateRaidAction $updateRaidAction,
        private DeleteRaidAction $deleteRaidAction,
        private SetRaidCompositionAction $setRaidCompositionAction,
        private CanManageRaidRecruitmentAction $canManageRaidRecruitmentAction,
        private SubmitRaidApplicationAction $submitRaidApplicationAction,
        private DecideRaidApplicationAction $decideRaidApplicationAction,
        private ListRaidDescendantUsersAction $listRaidDescendantUsersAction,
    ) {}

    /**
     * Дерево рейдов гильдии.
     */
    public function index(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $raids = ($this->listGuildRaidsAction)($guild, $request->user());

        return RaidResource::collection($raids);
    }

    /**
     * Один рейд гильдии.
     */
    public function show(Request $request, Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid, $request->user());
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }

        return response()->json(new RaidResource($model));
    }

    public function store(StoreRaidRequest $request, Guild $guild): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'guild_id' => $guild->id,
            'created_by' => $request->user()?->getKey(),
        ]);
        $raid = ($this->createRaidAction)($data);
        $raid->load('leader:id,name', 'parent:id,name');

        // Реалтайм-обновление дерева рейдов гильдии (best-effort).
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl.'/raids-tree/broadcast-updated', [
                'guildId' => $guild->id,
                'payload' => [
                    'kind' => 'created',
                    'raidId' => $raid->id,
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return (new RaidResource($raid))->response()->setStatusCode(201);
    }

    public function update(UpdateRaidRequest $request, Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        $updated = ($this->updateRaidAction)($model, $request->validated());

        // Реалтайм-обновление дерева рейдов гильдии (best-effort).
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl.'/raids-tree/broadcast-updated', [
                'guildId' => $guild->id,
                'payload' => [
                    'kind' => 'updated',
                    'raidId' => $updated->id,
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return response()->json(new RaidResource($updated));
    }

    public function destroy(Guild $guild, int $raid): Response
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        ($this->deleteRaidAction)($model);

        // Реалтайм-обновление дерева рейдов гильдии (best-effort).
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl.'/raids-tree/broadcast-updated', [
                'guildId' => $guild->id,
                'payload' => [
                    'kind' => 'deleted',
                    'raidId' => $raid,
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return response()->noContent();
    }

    /**
     * Установить состав рейда (участники и их слоты).
     */
    public function setComposition(SetRaidCompositionRequest $request, Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        $updated = ($this->setRaidCompositionAction)(
            $request->user(),
            $model,
            $request->validated()['members'],
        );
        $updated->load(['leader:id,name', 'parent:id,name', 'members:id,name']);
        $this->broadcastTreeUpdated($guild, $updated->id);

        // Реалтайм-обновление для всех, у кого открыт этот рейд.
        // Socket server — best-effort: не ломаем основной запрос, если сокеты недоступны.
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl.'/raids/broadcast-updated', [
                'guildId' => $guild->id,
                'raidId' => $updated->id,
                'raid' => (new RaidResource($updated))->resolve(),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return response()->json(new RaidResource($updated));
    }

    public function descendantUsers(Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }

        return response()->json([
            'data' => ($this->listRaidDescendantUsersAction)($model),
        ]);
    }

    public function updateRecruitment(
        UpdateRaidRecruitmentRequest $request,
        Guild $guild,
        int $raid,
    ): JsonResponse {
        $model = ($this->getRaidAction)($guild, $raid, $request->user());
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        $this->authorizeRecruitmentManagement($request, $model);

        if ($request->boolean('is_recruiting') && $model->children()->exists()) {
            throw ValidationException::withMessages([
                'is_recruiting' => ['Набор можно открыть только в рейд без дочерних рейдов.'],
            ]);
        }

        $model->update(['is_recruiting' => $request->boolean('is_recruiting')]);
        $this->broadcastTreeUpdated($guild, $model->id);

        return response()->json(new RaidResource($model->refresh()));
    }

    public function applications(Request $request, Guild $guild, int $raid): AnonymousResourceCollection
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        $this->authorizeRecruitmentManagement($request, $model);

        $applications = $model->applications()
            ->where('status', RaidApplication::STATUS_PENDING)
            ->with([
                'character.gameClasses',
                'character.characterGuildTags' => fn ($query) => $query->wherePivot('guild_id', $guild->id),
                'character.tags',
            ])
            ->oldest()
            ->get();

        return RaidApplicationResource::collection($applications);
    }

    public function submitApplication(
        SubmitRaidApplicationRequest $request,
        Guild $guild,
        int $raid,
    ): JsonResponse {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }

        $application = ($this->submitRaidApplicationAction)(
            $request->user(),
            $model,
            (int) $request->validated('character_id'),
        );
        $this->broadcastTreeUpdated($guild, $model->id);

        return (new RaidApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    public function acceptApplication(
        Request $request,
        Guild $guild,
        int $raid,
        RaidApplication $application,
    ): JsonResponse {
        return $this->decideApplication($request, $guild, $raid, $application, true);
    }

    public function rejectApplication(
        Request $request,
        Guild $guild,
        int $raid,
        RaidApplication $application,
    ): JsonResponse {
        return $this->decideApplication($request, $guild, $raid, $application, false);
    }

    private function decideApplication(
        Request $request,
        Guild $guild,
        int $raid,
        RaidApplication $application,
        bool $accept,
    ): JsonResponse {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null || (int) $application->raid_id !== $raid) {
            throw new NotFoundHttpException('Заявка не найдена.');
        }
        $this->authorizeRecruitmentManagement($request, $model);

        $decided = ($this->decideRaidApplicationAction)(
            $request->user(),
            $model,
            $application,
            $accept,
        );
        $decided->load(['character.gameClasses', 'character.characterGuildTags', 'character.tags']);
        $this->broadcastTreeUpdated($guild, $model->id);
        if ($accept) {
            $updatedRaid = ($this->getRaidAction)($guild, $model->id, $request->user());
            if ($updatedRaid) {
                try {
                    $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
                    Http::timeout(1.5)->post($socketUrl.'/raids/broadcast-updated', [
                        'guildId' => $guild->id,
                        'raidId' => $updatedRaid->id,
                        'raid' => (new RaidResource($updatedRaid))->resolve(),
                    ]);
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        return response()->json(new RaidApplicationResource($decided));
    }

    private function authorizeRecruitmentManagement(Request $request, $raid): void
    {
        if (! ($this->canManageRaidRecruitmentAction)($request->user(), $raid)) {
            abort(403, 'Недостаточно прав для управления набором в этот рейд.');
        }
    }

    private function broadcastTreeUpdated(Guild $guild, int $raidId): void
    {
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl.'/raids-tree/broadcast-updated', [
                'guildId' => $guild->id,
                'payload' => [
                    'kind' => 'updated',
                    'raidId' => $raidId,
                ],
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }
}
