<?php

namespace App\Services;

use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Отправка событий об оповещениях на socket_server по HTTP (best-effort).
 *
 * Socket-сервер ретранслирует события клиентам в комнате `user:{id}:notifications`.
 * Сбои сокета не должны ломать основной HTTP-флоу — все ошибки логируем и глотаем.
 */
class NotificationSocketBroadcaster
{
    private const DEFAULT_SOCKET_URL = 'http://socket-server-nodejs:3007';
    private const HTTP_TIMEOUT_SECONDS = 1.5;

    public function broadcastCreated(Notification $notification): void
    {
        $this->post('/notifications/broadcast-created', [
            'userId' => $notification->user_id,
            'notification' => (new NotificationResource($notification))->resolve(),
            'unreadCount' => $this->unreadCountFor($notification->user_id),
        ]);
    }

    /**
     * @param  int[]  $ids
     */
    public function broadcastDeleted(int $userId, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return;
        }

        $this->post('/notifications/broadcast-deleted', [
            'userId' => $userId,
            'ids' => $ids,
            'unreadCount' => $this->unreadCountFor($userId),
        ]);
    }

    public function broadcastRead(Notification $notification): void
    {
        if ($notification->read_at === null) {
            return;
        }

        $this->post('/notifications/broadcast-read', [
            'userId' => $notification->user_id,
            'id' => $notification->id,
            'readAt' => $notification->read_at->toIso8601String(),
            'unreadCount' => $this->unreadCountFor($notification->user_id),
        ]);
    }

    private function unreadCountFor(int $userId): int
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): void
    {
        $base = rtrim((string) env('SOCKET_SERVER_URL', self::DEFAULT_SOCKET_URL), '/');

        try {
            Http::timeout(self::HTTP_TIMEOUT_SECONDS)->post($base . $path, $payload);
        } catch (Throwable $e) {
            Log::debug('notification socket broadcast failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
