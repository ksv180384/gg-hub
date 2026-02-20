<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paginator = $user->notifications()
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        $unreadCount = $user->notifications()->whereNull('read_at')->count();

        return response()->json([
            'data' => NotificationResource::collection($paginator->items()),
            'unread_count' => $unreadCount,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return response()->json(['data' => (new NotificationResource($notification->fresh()))->resolve()]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $notification->delete();
        return response()->json(null, 204);
    }
}
