<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuildBank\RevokeGuildBankGrantRequest;
use App\Http\Requests\GuildBank\StoreGuildBankGrantRequest;
use App\Http\Requests\GuildBank\StoreGuildBankItemRequest;
use App\Http\Requests\GuildBank\StoreGuildBankItemTierRequest;
use App\Http\Requests\GuildBank\UpdateGuildBankItemRequest;
use App\Http\Requests\GuildBank\UpdateGuildBankItemTierRequest;
use App\Http\Resources\GuildBank\GuildBankGrantListResource;
use App\Http\Resources\GuildBank\GuildBankMemberGrantListResource;
use App\Http\Resources\GuildBank\GuildBankGrantResource;
use App\Http\Resources\GuildBank\GuildBankItemListResource;
use App\Http\Resources\GuildBank\GuildBankItemResource;
use App\Http\Resources\GuildBank\GuildBankItemTierListResource;
use App\Http\Resources\GuildBank\GuildBankItemTierResource;
use App\Http\Resources\GuildBank\GuildBankPageContextResource;
use Domains\Guild\Models\Guild;
use Domains\GuildBank\Actions\CreateGuildBankItemAction;
use Domains\GuildBank\Actions\CreateGuildBankItemGrantAction;
use Domains\GuildBank\Actions\CreateGuildBankItemTierAction;
use Domains\GuildBank\Actions\DeleteGuildBankItemAction;
use Domains\GuildBank\Actions\DeleteGuildBankItemTierAction;
use Domains\GuildBank\Actions\GetGuildBankPageContextAction;
use Domains\GuildBank\Actions\ListGuildBankItemGrantsAction;
use Domains\GuildBank\Actions\ListGuildBankItemsAction;
use Domains\GuildBank\Actions\ListGuildBankItemTiersAction;
use Domains\GuildBank\Actions\ListGuildMemberBankGrantsAction;
use Domains\GuildBank\Actions\RevokeGuildBankItemGrantAction;
use Domains\GuildBank\Actions\UpdateGuildBankItemAction;
use Domains\GuildBank\Actions\UpdateGuildBankItemTierAction;
use Domains\GuildBank\Models\GuildBankItem;
use Domains\GuildBank\Models\GuildBankItemGrant;
use Domains\GuildBank\Models\GuildBankItemTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
        private ListGuildBankItemTiersAction $listGuildBankItemTiersAction,
        private CreateGuildBankItemTierAction $createGuildBankItemTierAction,
        private UpdateGuildBankItemTierAction $updateGuildBankItemTierAction,
        private DeleteGuildBankItemTierAction $deleteGuildBankItemTierAction,
        private GetGuildBankPageContextAction $getGuildBankPageContextAction,
    ) {}

    public function pageContext(Guild $guild): GuildBankPageContextResource
    {
        $user = request()->user();
        abort_unless($user !== null, 403);

        return new GuildBankPageContextResource(
            ($this->getGuildBankPageContextAction)($guild, $user)
        );
    }

    public function tiers(Guild $guild): AnonymousResourceCollection
    {
        $tiers = ($this->listGuildBankItemTiersAction)($guild);

        return GuildBankItemTierListResource::collection($tiers);
    }

    public function storeTier(StoreGuildBankItemTierRequest $request, Guild $guild): JsonResponse
    {
        $tier = ($this->createGuildBankItemTierAction)($guild, $request->validated());

        return (new GuildBankItemTierResource($tier))->response()->setStatusCode(201);
    }

    public function updateTier(UpdateGuildBankItemTierRequest $request, Guild $guild, GuildBankItemTier $tier): GuildBankItemTierResource
    {
        if ((int) $tier->guild_id !== (int) $guild->id) {
            abort(404);
        }

        $updated = ($this->updateGuildBankItemTierAction)($tier, $request->validated());

        return new GuildBankItemTierResource($updated);
    }

    public function destroyTier(Guild $guild, GuildBankItemTier $tier): JsonResponse|Response
    {
        if ((int) $tier->guild_id !== (int) $guild->id) {
            abort(404);
        }

        ($this->deleteGuildBankItemTierAction)($tier);

        return response()->noContent();
    }

    public function items(Guild $guild): AnonymousResourceCollection
    {
        $items = ($this->listGuildBankItemsAction)($guild);
        return GuildBankItemListResource::collection($items);
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
        return GuildBankGrantListResource::collection($grants);
    }

    public function storeGrant(StoreGuildBankGrantRequest $request, Guild $guild): JsonResponse
    {
        $grant = ($this->createGuildBankItemGrantAction)(
            $guild,
            $request->validated(),
            $request->user(),
        );
        $grant->load([
            'item:id,name,guild_bank_item_tier_id,dkp_cost,quantity',
            'item.tier',
            'receivedByCharacter:id,name',
            'grantedByCharacter:id,name',
        ]);
        return (new GuildBankGrantResource($grant))->response()->setStatusCode(201);
    }

    public function revokeGrant(RevokeGuildBankGrantRequest $request, Guild $guild, int $grant): JsonResponse
    {
        $grantModel = GuildBankItemGrant::query()
            ->where('guild_id', $guild->id)
            ->whereKey($grant)
            ->firstOrFail();

        $grantId = (int) $grantModel->id;
        ($this->revokeGuildBankItemGrantAction)($guild, $grantModel, $request->user());

        return response()->json([
            'message' => 'Выдача отменена.',
            'data' => ['id' => $grantId],
        ]);
    }

    public function memberGrants(Guild $guild, int $character): AnonymousResourceCollection
    {
        $grants = ($this->listGuildMemberBankGrantsAction)($guild, $character);
        return GuildBankMemberGrantListResource::collection($grants);
    }
}
