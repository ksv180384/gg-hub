<?php

namespace App\Services;

use App\Http\Resources\ConstantParty\ConstantPartyChatMessageResource;
use Domains\ConstantParty\Models\ConstantPartyChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConstantPartyChatSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';

    private const HTTP_TIMEOUT_SECONDS = 1.5;

    public function broadcastCreated(ConstantPartyChatMessage $message): void
    {
        $this->post('/constant-party-chat/broadcast-created', [
            'partyId' => $message->constant_party_id,
            'message' => (new ConstantPartyChatMessageResource($message))->resolve(),
        ]);
    }

    /** @param array<int, array<string, int|string>> $messages */
    public function broadcastReceiptsChanged(int $partyId, array $messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->post('/constant-party-chat/broadcast-receipts', [
            'partyId' => $partyId,
            'messages' => array_values($messages),
        ]);
    }

    public function broadcastDeleted(int $partyId, int $messageId): void
    {
        $this->post('/constant-party-chat/broadcast-deleted', [
            'partyId' => $partyId,
            'messageId' => $messageId,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload): void
    {
        if (! config('features.constant_party_chat')) {
            return;
        }

        $base = rtrim((string) env('SOCKET_SERVER_URL', self::DEFAULT_SOCKET_URL), '/');
        $internalToken = (string) env('SOCKET_SERVER_INTERNAL_TOKEN', '');
        if ($internalToken === '') {
            Log::warning('constant party chat socket broadcast skipped: internal token is missing');

            return;
        }

        try {
            Http::withHeaders(['X-Socket-Internal-Token' => $internalToken])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->post($base.$path, $payload);
        } catch (Throwable $exception) {
            Log::debug('constant party chat socket broadcast failed', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
