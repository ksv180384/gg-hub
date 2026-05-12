<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Filters\GuildDkpLedgerFilter;
use App\Http\Requests\GuildDkp\AdjustGuildUserDkpRequest;
use App\Http\Requests\GuildDkp\ListGuildDkpLedgerRequest;
use App\Http\Resources\GuildDkp\GuildDkpLedgerEntryListResource;
use App\Http\Resources\GuildDkp\GuildUserDkpBalanceResource;
use Domains\Guild\Models\Guild;
use Domains\GuildDkp\Actions\AdjustGuildUserDkpAction;
use Domains\GuildDkp\Actions\GetGuildUserDkpBalanceAction;
use Domains\GuildDkp\Actions\ListGuildDkpLedgerAction;
use Domains\GuildDkp\Actions\ResolveGuildMemberUserIdAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuildDkpController extends Controller
{
    public function __construct(
        private ListGuildDkpLedgerAction $listGuildDkpLedgerAction,
        private GetGuildUserDkpBalanceAction $getGuildUserDkpBalanceAction,
        private AdjustGuildUserDkpAction $adjustGuildUserDkpAction,
        private ResolveGuildMemberUserIdAction $resolveGuildMemberUserIdAction,
    ) {}

    public function ledger(ListGuildDkpLedgerRequest $request, Guild $guild): AnonymousResourceCollection|JsonResponse
    {
        if (! (bool) ($guild->dkp_enabled ?? false)) {
            return response()->json([
                'message' => 'Система ДКП отключена в этой гильдии.',
            ], 422);
        }

        $filter = new GuildDkpLedgerFilter($request);

        return GuildDkpLedgerEntryListResource::collection(
            ($this->listGuildDkpLedgerAction)($guild, $filter)
        );
    }

    public function memberBalance(Guild $guild, int $character): GuildUserDkpBalanceResource|JsonResponse
    {
        if (! (bool) ($guild->dkp_enabled ?? false)) {
            return response()->json([
                'message' => 'Система ДКП отключена в этой гильдии.',
            ], 422);
        }

        $userId = ($this->resolveGuildMemberUserIdAction)($guild, $character);

        return new GuildUserDkpBalanceResource([
            'user_id' => $userId,
            'balance' => ($this->getGuildUserDkpBalanceAction)($guild, $userId),
        ]);
    }

    public function adjustMemberBalance(
        AdjustGuildUserDkpRequest $request,
        Guild $guild,
        int $character,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $userId = ($this->resolveGuildMemberUserIdAction)($guild, $character);
        $entry = ($this->adjustGuildUserDkpAction)($guild, $userId, $user, [
            ...$request->validated(),
            'character_id' => $character,
        ]);

        return (new GuildDkpLedgerEntryListResource($entry->load([
            'user:id,name',
            'actorUser:id,name',
            'character:id,name',
        ])))->response()->setStatusCode(201);
    }
}
