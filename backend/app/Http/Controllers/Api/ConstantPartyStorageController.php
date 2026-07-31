<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConstantParty\StoreConstantPartyStorageGrantRequest;
use App\Http\Requests\ConstantParty\StoreConstantPartyStorageItemRequest;
use App\Http\Requests\ConstantParty\StoreConstantPartyStorageTierRequest;
use App\Http\Resources\ConstantParty\ConstantPartyFormerMemberResource;
use App\Http\Resources\ConstantParty\ConstantPartyStorageGrantResource;
use App\Http\Resources\ConstantParty\ConstantPartyStorageItemResource;
use App\Http\Resources\ConstantParty\ConstantPartyStorageItemTierResource;
use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyFormerMember;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Domains\ConstantParty\Models\ConstantPartyStorageItem;
use Domains\ConstantParty\Models\ConstantPartyStorageItemGrant;
use Domains\ConstantParty\Models\ConstantPartyStorageItemTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ConstantPartyStorageController extends Controller
{
    public function context(Request $request, ConstantParty $constantParty): JsonResponse
    {
        $member = $this->ensureMember($constantParty, $request->user()->id);

        return response()->json([
            'data' => [
                'can_manage_storage' => $member->role === ConstantPartyMember::ROLE_LEADER || $member->can_manage_storage,
                'my_member_id' => $member->id,
                'my_character_id' => $member->character_id,
            ],
        ]);
    }

    public function tiers(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $tiers = ConstantPartyStorageItemTier::query()
            ->where('constant_party_id', $constantParty->id)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ConstantPartyStorageItemTierResource::collection($tiers);
    }

    public function storeTier(StoreConstantPartyStorageTierRequest $request, ConstantParty $constantParty): JsonResponse
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $data = $request->validated();

        $tier = ConstantPartyStorageItemTier::query()->create([
            'constant_party_id' => $constantParty->id,
            'name' => trim((string) $data['name']),
            'color' => $data['color'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return (new ConstantPartyStorageItemTierResource($tier))->response()->setStatusCode(201);
    }

    public function updateTier(StoreConstantPartyStorageTierRequest $request, ConstantParty $constantParty, ConstantPartyStorageItemTier $tier): ConstantPartyStorageItemTierResource
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $this->ensureTierBelongsToParty($constantParty, $tier);
        $data = $request->validated();

        $tier->update([
            'name' => trim((string) $data['name']),
            'color' => $data['color'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return new ConstantPartyStorageItemTierResource($tier);
    }

    public function destroyTier(Request $request, ConstantParty $constantParty, ConstantPartyStorageItemTier $tier): Response
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $this->ensureTierBelongsToParty($constantParty, $tier);
        if ($tier->items()->exists()) {
            abort(422, 'Нельзя удалить тир, к которому привязаны предметы.');
        }

        $tier->delete();

        return response()->noContent();
    }

    public function items(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $items = ConstantPartyStorageItem::query()
            ->where('constant_party_id', $constantParty->id)
            ->with(['tier', 'createdByCharacter', 'updatedByCharacter'])
            ->withCount('grants')
            ->orderBy('name')
            ->get();

        return ConstantPartyStorageItemResource::collection($items);
    }

    public function formerMembers(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $formerMembers = ConstantPartyFormerMember::query()
            ->where('constant_party_id', $constantParty->id)
            ->whereNotIn(
                'character_id',
                ConstantPartyMember::query()
                    ->select('character_id')
                    ->where('constant_party_id', $constantParty->id),
            )
            ->with(['character.gameClasses', 'character.server'])
            ->orderByDesc('left_at')
            ->get();

        return ConstantPartyFormerMemberResource::collection($formerMembers);
    }

    public function characterGrants(
        Request $request,
        ConstantParty $constantParty,
        Character $character,
    ): AnonymousResourceCollection {
        $this->ensureMember($constantParty, $request->user()->id);

        $isKnownCharacter = ConstantPartyMember::query()
            ->where('constant_party_id', $constantParty->id)
            ->where('character_id', $character->id)
            ->exists()
            || ConstantPartyFormerMember::query()
                ->where('constant_party_id', $constantParty->id)
                ->where('character_id', $character->id)
                ->exists();

        if (! $isKnownCharacter) {
            abort(404);
        }

        $grants = ConstantPartyStorageItemGrant::query()
            ->where('constant_party_id', $constantParty->id)
            ->where('received_by_character_id', $character->id)
            ->with(['item.tier', 'receivedByCharacter', 'grantedByCharacter'])
            ->orderByDesc('granted_at')
            ->get();

        return ConstantPartyStorageGrantResource::collection($grants);
    }

    public function storeItem(StoreConstantPartyStorageItemRequest $request, ConstantParty $constantParty): JsonResponse
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $data = $request->validated();
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, (int) $data['actor_character_id']);

        $item = ConstantPartyStorageItem::query()->create([
            'constant_party_id' => $constantParty->id,
            'tier_id' => $data['tier_id'] ?? null,
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'quantity' => $data['quantity'] ?? null,
            'created_by_character_id' => $data['actor_character_id'],
        ]);
        $item->load(['tier', 'createdByCharacter']);

        return (new ConstantPartyStorageItemResource($item))->response()->setStatusCode(201);
    }

    public function updateItem(StoreConstantPartyStorageItemRequest $request, ConstantParty $constantParty, ConstantPartyStorageItem $item): ConstantPartyStorageItemResource
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $this->ensureItemBelongsToParty($constantParty, $item);
        $data = $request->validated();
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, (int) $data['actor_character_id']);

        $item->update([
            'tier_id' => $data['tier_id'] ?? null,
            'name' => trim((string) $data['name']),
            'description' => isset($data['description']) ? trim((string) $data['description']) : null,
            'quantity' => $data['quantity'] ?? null,
            'updated_by_character_id' => $data['actor_character_id'],
        ]);
        $item->load(['tier', 'createdByCharacter', 'updatedByCharacter']);

        return new ConstantPartyStorageItemResource($item);
    }

    public function destroyItem(Request $request, ConstantParty $constantParty, ConstantPartyStorageItem $item): Response
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $this->ensureItemBelongsToParty($constantParty, $item);
        if ($item->grants()->exists()) {
            abort(422, 'Нельзя удалить предмет с историей выдачи.');
        }

        $item->delete();

        return response()->noContent();
    }

    public function itemGrants(Request $request, ConstantParty $constantParty, ConstantPartyStorageItem $item): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);
        $this->ensureItemBelongsToParty($constantParty, $item);

        $grants = ConstantPartyStorageItemGrant::query()
            ->where('constant_party_id', $constantParty->id)
            ->where('item_id', $item->id)
            ->with(['item.tier', 'receivedByCharacter', 'grantedByCharacter'])
            ->orderByDesc('granted_at')
            ->get();

        return ConstantPartyStorageGrantResource::collection($grants);
    }

    public function storeGrant(StoreConstantPartyStorageGrantRequest $request, ConstantParty $constantParty): JsonResponse
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        $data = $request->validated();
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, (int) $data['granted_by_character_id']);

        $grant = DB::transaction(function () use ($constantParty, $data): ConstantPartyStorageItemGrant {
            /** @var ConstantPartyStorageItem $item */
            $item = ConstantPartyStorageItem::query()
                ->where('constant_party_id', $constantParty->id)
                ->whereKey($data['item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->quantity !== null && (int) $item->quantity <= 0) {
                abort(422, 'Недостаточно предметов на складе для выдачи.');
            }

            $grant = ConstantPartyStorageItemGrant::query()->create([
                'constant_party_id' => $constantParty->id,
                'item_id' => $item->id,
                'received_by_character_id' => $data['received_by_character_id'],
                'granted_by_character_id' => $data['granted_by_character_id'],
                'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
                'granted_at' => $data['granted_at'] ?? now(),
            ]);

            if ($item->quantity !== null) {
                $item->quantity = max(0, (int) $item->quantity - 1);
                $item->save();
            }

            return $grant;
        });

        $grant->load(['item.tier', 'receivedByCharacter', 'grantedByCharacter']);

        return (new ConstantPartyStorageGrantResource($grant))->response()->setStatusCode(201);
    }

    public function revokeGrant(Request $request, ConstantParty $constantParty, ConstantPartyStorageItemGrant $grant): Response
    {
        $this->ensureStorageManager($constantParty, $request->user()->id);
        if ((int) $grant->constant_party_id !== (int) $constantParty->id) {
            abort(404);
        }

        DB::transaction(function () use ($grant): void {
            /** @var ConstantPartyStorageItem $item */
            $item = ConstantPartyStorageItem::query()->whereKey($grant->item_id)->lockForUpdate()->firstOrFail();
            if ($item->quantity !== null) {
                $item->quantity = (int) $item->quantity + 1;
                $item->save();
            }
            $grant->delete();
        });

        return response()->noContent();
    }

    private function ensureMember(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = ConstantPartyMember::query()
            ->where('constant_party_id', $party->id)
            ->whereHas('character', fn ($query) => $query->where('user_id', $userId))
            ->first();

        if (! $member) {
            abort(403);
        }

        return $member;
    }

    private function ensureStorageManager(ConstantParty $party, int $userId): ConstantPartyMember
    {
        $member = $this->ensureMember($party, $userId);
        if ($member->role !== ConstantPartyMember::ROLE_LEADER && ! $member->can_manage_storage) {
            abort(403);
        }

        return $member;
    }

    private function ensureUserOwnsPartyCharacter(ConstantParty $party, int $userId, int $characterId): void
    {
        $exists = ConstantPartyMember::query()
            ->where('constant_party_id', $party->id)
            ->where('character_id', $characterId)
            ->whereHas('character', fn ($query) => $query->where('user_id', $userId))
            ->exists();

        if (! $exists) {
            abort(403);
        }
    }

    private function ensureTierBelongsToParty(ConstantParty $party, ConstantPartyStorageItemTier $tier): void
    {
        if ((int) $tier->constant_party_id !== (int) $party->id) {
            abort(404);
        }
    }

    private function ensureItemBelongsToParty(ConstantParty $party, ConstantPartyStorageItem $item): void
    {
        if ((int) $item->constant_party_id !== (int) $party->id) {
            abort(404);
        }
    }
}
