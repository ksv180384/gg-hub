<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\DeleteNotificationAction;
use App\Actions\Notification\ListNotificationsAction;
use App\Actions\Notification\MarkNotificationReadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private ListNotificationsAction $listNotificationsAction,
        private MarkNotificationReadAction $markNotificationReadAction,
        private DeleteNotificationAction $deleteNotificationAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = ($this->listNotificationsAction)($user);
        $paginator = $result['paginator'];
        return response()->json([
            'data' => NotificationResource::collection($paginator->items()),
            'unread_count' => $result['unread_count'],
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $notification = ($this->markNotificationReadAction)($request->user(), $notification);
        return response()->json(['data' => (new NotificationResource($notification))->resolve()]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        ($this->deleteNotificationAction)($request->user(), $notification);
        return response()->json(null, 204);
    }
}
