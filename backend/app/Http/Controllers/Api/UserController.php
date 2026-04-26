<?php

namespace App\Http\Controllers\Api;

use App\Actions\User\GetCurrentUserAction;
use App\Actions\User\UpdateUserProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\Event\UserGuildCalendarEventResource;
use App\Http\Resources\Poll\UserPollResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Guild\GuildApplicationResource;
use Domains\Event\Actions\ListUserGuildCalendarEventsAction;
use Domains\Guild\Actions\GetUserGuildsForGameAction;
use Domains\Guild\Actions\ListUserGuildApplicationsAction;
use Domains\Poll\Actions\ListUserPollsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    public function __construct(
        private GetCurrentUserAction $getCurrentUserAction,
        private UpdateUserProfileAction $updateUserProfileAction,
        private GetUserGuildsForGameAction $getUserGuildsForGameAction,
        private ListUserGuildApplicationsAction $listUserGuildApplicationsAction,
        private ListUserPollsAction $listUserPollsAction,
        private ListUserGuildCalendarEventsAction $listUserGuildCalendarEventsAction
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = ($this->getCurrentUserAction)($request->user());
        return response()->json(['user' => $user ? (new UserResource($user))->resolve() : null]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user = ($this->updateUserProfileAction)(
            $user,
            $request->validated(),
            $request->file('avatar')
        );

        return response()->json(['user' => (new UserResource($user))->resolve()]);
    }

    /**
     * Гильдии текущей игры, в которых состоит пользователь (по персонажам).
     * Query: game_id (обязательно).
     */
    public function guilds(Request $request): JsonResponse
    {
        $gameId = (int) $request->query('game_id');
        if ($gameId < 1) {
            return response()->json(['data' => []], 200);
        }
        $guilds = ($this->getUserGuildsForGameAction)($request->user(), $gameId);
        return response()->json(['data' => $guilds->values()->all()]);
    }

    /**
     * Голосования: активные + закрытые не более 3 суток назад из гильдий пользователя.
     * Query: game_id (опционально) — фильтр по игре.
     */
    public function polls(Request $request): JsonResponse
    {
        $user = $request->user();
        $gameId = $request->query('game_id');
        $gameId = $gameId !== null && $gameId !== '' ? (int) $gameId : null;
        $gameId = $gameId > 0 ? $gameId : null;

        $polls = ($this->listUserPollsAction)($user, $gameId);

        return UserPollResource::collection($polls)->response();
    }

    /**
     * События календаря пользователя по всем его гильдиям за период.
     * Query: from (Y-m-d), to (Y-m-d). Если не переданы — используется «сегодня» в timezone пользователя.
     */
    public function guildCalendarEvents(Request $request): JsonResponse
    {
        $user = $request->user();

        $tz = $user?->timezone ?: 'UTC';
        $today = Carbon::now($tz)->toDateString();

        $from = $request->query('from');
        $to = $request->query('to');
        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : $today;
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $from;

        $events = ($this->listUserGuildCalendarEventsAction)($user, [
            'from' => $from,
            'to' => $to,
        ]);

        return UserGuildCalendarEventResource::collection($events)->response();
    }

    /**
     * Заявки пользователя во все гильдии.
     */
    public function applications(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $paginator = ($this->listUserGuildApplicationsAction)($user, $perPage);

        return response()->json([
            'data' => GuildApplicationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
