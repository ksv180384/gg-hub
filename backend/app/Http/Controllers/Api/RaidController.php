<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\SetRaidCompositionRequest;
use App\Http\Requests\Raid\StoreRaidRequest;
use App\Http\Requests\Raid\UpdateRaidRequest;
use App\Http\Resources\Raid\RaidResource;
use Domains\Guild\Models\Guild;
use Domains\Raid\Actions\CreateRaidAction;
use Domains\Raid\Actions\DeleteRaidAction;
use Domains\Raid\Actions\GetRaidAction;
use Domains\Raid\Actions\ListGuildRaidsAction;
use Domains\Raid\Actions\SetRaidCompositionAction;
use Domains\Raid\Actions\UpdateRaidAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RaidController extends Controller
{
    public function __construct(
        private ListGuildRaidsAction $listGuildRaidsAction,
        private GetRaidAction $getRaidAction,
        private CreateRaidAction $createRaidAction,
        private UpdateRaidAction $updateRaidAction,
        private DeleteRaidAction $deleteRaidAction,
        private SetRaidCompositionAction $setRaidCompositionAction
    ) {}

    /**
     * Дерево рейдов гильдии.
     */
    public function index(Guild $guild): AnonymousResourceCollection
    {
        $raids = ($this->listGuildRaidsAction)($guild);
        return RaidResource::collection($raids);
    }

    /**
     * Один рейд гильдии.
     */
    public function show(Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid);
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
            Http::timeout(1.5)->post($socketUrl . '/raids-tree/broadcast-updated', [
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
            Http::timeout(1.5)->post($socketUrl . '/raids-tree/broadcast-updated', [
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
            Http::timeout(1.5)->post($socketUrl . '/raids-tree/broadcast-updated', [
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
        $updated = ($this->setRaidCompositionAction)($model, $request->validated()['members']);
        $updated->load(['leader:id,name', 'parent:id,name', 'members:id,name']);

        // Реалтайм-обновление для всех, у кого открыт этот рейд.
        // Socket server — best-effort: не ломаем основной запрос, если сокеты недоступны.
        try {
            $socketUrl = rtrim((string) env('SOCKET_SERVER_URL', 'http://socket-server-nodejs:3007'), '/');
            Http::timeout(1.5)->post($socketUrl . '/raids/broadcast-updated', [
                'guildId' => $guild->id,
                'raidId' => $updated->id,
                'raid' => (new RaidResource($updated))->resolve(),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return response()->json(new RaidResource($updated));
    }
}
