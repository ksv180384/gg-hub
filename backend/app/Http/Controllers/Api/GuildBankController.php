<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuildBank\RevokeGuildBankGrantRequest;
use App\Http\Requests\GuildBank\StoreGuildBankGrantRequest;
use App\Http\Requests\GuildBank\StoreGuildBankItemRequest;
use App\Http\Requests\GuildBank\UpdateGuildBankItemRequest;
use App\Http\Resources\GuildBank\GuildBankGrantResource;
use App\Http\Resources\GuildBank\GuildBankItemResource;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Actions\CreateGuildBankItemAction;
use Domains\GuildBank\Actions\CreateGuildBankItemGrantAction;
use Domains\GuildBank\Actions\DeleteGuildBankItemAction;
use Domains\GuildBank\Actions\ListGuildBankItemGrantsAction;
use Domains\GuildBank\Actions\ListGuildBankItemsAction;
use Domains\GuildBank\Actions\ListGuildMemberBankGrantsAction;
use Domains\GuildBank\Actions\RevokeGuildBankItemGrantAction;
use Domains\GuildBank\Actions\UpdateGuildBankItemAction;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildBankController extends Controller
{
    public function __construct(
        private ListGuildBankItemsAction $listGuildBankItemsAction,
        private CreateGuildBankItemAction $createGuildBankItemAction,
        private UpdateGuildBankItemAction $updateGuildBankItemAction,
        private DeleteGuildBankItemAction $deleteGuildBankItemAction,
        private ListGuildBankItemGrantsAction $listGuildBankItemGrantsAction,
        private CreateGuildBankItemGrantAction $createGuildBankItemGrantAction,
        private RevokeGuildBankItemGrantAction $revokeGuildBankItemGrantAction,
        private ListGuildMemberBankGrantsAction $listGuildMemberBankGrantsAction,
    ) {}

    public function items(Guild $guild): AnonymousResourceCollection
    {
        $items = ($this->listGuildBankItemsAction)($guild);
        return GuildBankItemResource::collection($items);
    }

    public function storeItem(StoreGuildBankItemRequest $request, Guild $guild): JsonResponse
    {
        $item = ($this->createGuildBankItemAction)($guild, $request->validated());
        return (new GuildBankItemResource($item))->response()->setStatusCode(201);
    }

    public function updateItem(UpdateGuildBankItemRequest $request, Guild $guild, GuildBankItem $item): GuildBankItemResource
    {
        if ((int) $item->guild_id !== (int) $guild->id) {
            abort(404);
        }
        $updated = ($this->updateGuildBankItemAction)($item, $request->validated());
        return new GuildBankItemResource($updated);
    }

    public function destroyItem(Guild $guild, GuildBankItem $item): JsonResponse
    {
        if ((int) $item->guild_id !== (int) $guild->id) {
            abort(404);
        }
        ($this->deleteGuildBankItemAction)($item);
        return response()->json(['message' => 'Предмет удалён.']);
    }

    public function itemGrants(Guild $guild, GuildBankItem $item): AnonymousResourceCollection
    {
        if ((int) $item->guild_id !== (int) $guild->id) {
            abort(404);
        }
        $grants = ($this->listGuildBankItemGrantsAction)($guild, $item);
        return GuildBankGrantResource::collection($grants);
    }

    public function storeGrant(StoreGuildBankGrantRequest $request, Guild $guild): JsonResponse
    {
        $grant = ($this->createGuildBankItemGrantAction)($guild, $request->validated());
        $grant->load(['item:id,name,tier,color,dkp_cost,quantity', 'receivedByCharacter:id,name', 'grantedByCharacter:id,name']);
        return (new GuildBankGrantResource($grant))->response()->setStatusCode(201);
    }

    public function revokeGrant(RevokeGuildBankGrantRequest $request, Guild $guild, int $grant): JsonResponse
    {
        $grantModel = GuildBankItemGrant::query()
            ->where('guild_id', $guild->id)
            ->whereKey($grant)
            ->firstOrFail();

        $grantId = (int) $grantModel->id;
        ($this->revokeGuildBankItemGrantAction)($grantModel);

        return response()->json([
            'message' => 'Выдача отменена.',
            'data' => ['id' => $grantId],
        ]);
    }

    public function memberGrants(Guild $guild, int $character): AnonymousResourceCollection
    {
        $grants = ($this->listGuildMemberBankGrantsAction)($guild, $character);
        return GuildBankGrantResource::collection($grants);
    }
}

