<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConstantParty\StoreConstantPartyChatMessageRequest;
use App\Http\Resources\ConstantParty\ConstantPartyChatMessageResource;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\ConstantParty\Models\ConstantPartyChatMessage;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ConstantPartyChatController extends Controller
{
    public function index(Request $request, ConstantParty $constantParty): AnonymousResourceCollection
    {
        $this->ensureMember($constantParty, $request->user()->id);

        $messages = ConstantPartyChatMessage::query()
            ->where('constant_party_id', $constantParty->id)
            ->with('character.gameClasses')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return ConstantPartyChatMessageResource::collection($messages);
    }

    public function store(StoreConstantPartyChatMessageRequest $request, ConstantParty $constantParty): ConstantPartyChatMessageResource
    {
        $this->ensureMember($constantParty, $request->user()->id);
        $data = $request->validated();
        $this->ensureUserOwnsPartyCharacter($constantParty, $request->user()->id, (int) $data['character_id']);

        $message = ConstantPartyChatMessage::query()->create([
            'constant_party_id' => $constantParty->id,
            'character_id' => $data['character_id'],
            'body' => trim((string) $data['body']),
        ]);
        $message->load('character.gameClasses');

        return new ConstantPartyChatMessageResource($message);
    }

    public function destroy(Request $request, ConstantParty $constantParty, ConstantPartyChatMessage $message): Response
    {
        $member = $this->ensureMember($constantParty, $request->user()->id);
        if ((int) $message->constant_party_id !== (int) $constantParty->id) {
            abort(404);
        }
        $ownsMessage = $message->character()->where('user_id', $request->user()->id)->exists();
        if (! $ownsMessage && $member->role !== ConstantPartyMember::ROLE_LEADER) {
            abort(403);
        }

        $message->delete();

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
}
