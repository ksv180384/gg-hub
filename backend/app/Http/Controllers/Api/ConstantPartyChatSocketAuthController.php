<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Character\CharacterResource;
use Domains\ConstantParty\Models\ConstantPartyChatSocketToken;
use Domains\ConstantParty\Models\ConstantPartyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConstantPartyChatSocketAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $socketToken = ConstantPartyChatSocketToken::query()
            ->where('token_hash', hash('sha256', $data['token']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $socketToken) {
            abort(401);
        }

        $member = ConstantPartyMember::query()
            ->where('constant_party_id', $socketToken->constant_party_id)
            ->where('character_id', $socketToken->character_id)
            ->with('character.gameClasses')
            ->first();

        if (! $member || (int) $member->character->user_id !== (int) $socketToken->user_id) {
            $socketToken->delete();
            abort(403);
        }

        $character = (new CharacterResource($member->character))->resolve();

        return response()->json([
            'data' => [
                'party_id' => (int) $socketToken->constant_party_id,
                'character' => [
                    'id' => (int) $member->character_id,
                    'name' => $member->character->name,
                    'avatar_url' => $character['avatar_url'] ?? null,
                ],
            ],
        ]);
    }
}
