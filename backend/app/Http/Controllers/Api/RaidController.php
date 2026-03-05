<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\StoreRaidRequest;
use App\Http\Requests\Raid\UpdateRaidRequest;
use App\Http\Resources\Raid\RaidResource;
use Domains\Guild\Models\Guild;
use Domains\Raid\Actions\CreateRaidAction;
use Domains\Raid\Actions\DeleteRaidAction;
use Domains\Raid\Actions\GetRaidAction;
use Domains\Raid\Actions\ListGuildRaidsAction;
use Domains\Raid\Actions\UpdateRaidAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RaidController extends Controller
{
    public function __construct(
        private ListGuildRaidsAction $listGuildRaidsAction,
        private GetRaidAction $getRaidAction,
        private CreateRaidAction $createRaidAction,
        private UpdateRaidAction $updateRaidAction,
        private DeleteRaidAction $deleteRaidAction
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
        return (new RaidResource($raid))->response()->setStatusCode(201);
    }

    public function update(UpdateRaidRequest $request, Guild $guild, int $raid): JsonResponse
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        $updated = ($this->updateRaidAction)($model, $request->validated());
        return response()->json(new RaidResource($updated));
    }

    public function destroy(Guild $guild, int $raid): Response
    {
        $model = ($this->getRaidAction)($guild, $raid);
        if ($model === null) {
            throw new NotFoundHttpException('Рейд не найден.');
        }
        ($this->deleteRaidAction)($model);
        return response()->noContent();
    }
}
