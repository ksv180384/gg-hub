<?php

namespace App\Http\Controllers\Api;

use App\Actions\Notification\CreateGuildApplicationNotificationAction;
use App\Actions\Notification\CreateGuildApplicationRejectedNotificationAction;
use App\Actions\Notification\CreateGuildApplicationApprovedNotificationAction;
use App\Actions\Notification\CreateGuildInvitationNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\SendGuildInvitationRequest;
use App\Http\Requests\Guild\SubmitGuildApplicationRequest;
use App\Http\Resources\Guild\GuildApplicationResource;
use Domains\Guild\Actions\ApproveGuildApplicationAction;
use Domains\Guild\Actions\CreateGuildInvitationAction;
use Domains\Guild\Actions\ListGuildApplicationsAction;
use Domains\Guild\Actions\RejectGuildApplicationAction;
use Domains\Guild\Actions\SubmitGuildApplicationAction;
use Domains\Guild\Models\Guild;
use Domains\Guild\Models\GuildApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuildApplicationController extends Controller
{
    public function __construct(
        private SubmitGuildApplicationAction $submitAction,
        private CreateGuildInvitationAction $createInvitationAction,
        private CreateGuildApplicationNotificationAction $createNotificationAction,
        private CreateGuildInvitationNotificationAction $createInvitationNotificationAction,
        private CreateGuildApplicationRejectedNotificationAction $createRejectedNotificationAction,
        private CreateGuildApplicationApprovedNotificationAction $createApprovedNotificationAction,
        private ListGuildApplicationsAction $listAction,
        private ApproveGuildApplicationAction $approveAction,
        private RejectGuildApplicationAction $rejectAction
    ) {}

    /**
     * Список заявок в гильдию. Доступно участникам с правом «Просмотр заявок в гильдию».
     */
    public function index(Request $request, Guild $guild): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;
        $paginator = ($this->listAction)($guild, $perPage);

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

    /**
     * Одна заявка. Доступно участникам с правом «Просмотр заявок в гильдию».
     */
    public function show(Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }
        $application->load(['character.gameClasses', 'character.game', 'guild.applicationFormFields', 'invitedByCharacter']);

        return response()->json(new GuildApplicationResource($application));
    }

    /**
     * Подать заявку в гильдию (авторизованный пользователь, персонаж + данные формы).
     */
    public function store(SubmitGuildApplicationRequest $request, Guild $guild): JsonResponse
    {
        $guild->load('applicationFormFields');
        $characterId = (int) $request->validated('character_id');
        $formData = $request->validated('form_data');
        $application = ($this->submitAction)($guild, $characterId, $formData);

        $applicationLink = '/guilds/' . $guild->id . '/applications/list/' . $application->id;
        ($this->createNotificationAction)($application, $applicationLink);

        return (new GuildApplicationResource($application))->response()->setStatusCode(201);
    }

    /**
     * Отправить приглашение в гильдию персонажу. Право «Подтверждение или отклонение заявок».
     * В приглашении сохраняется персонаж текущего пользователя из гильдии (с правом приглашения).
     */
    public function invite(SendGuildInvitationRequest $request, Guild $guild): JsonResponse
    {
        $characterId = (int) $request->validated('character_id');
        $inviterCharacterId = \Domains\Guild\Models\GuildMember::query()
            ->where('guild_id', $guild->id)
            ->whereHas('character', fn ($q) => $q->where('user_id', $request->user()->id))
            ->value('character_id');
        if (!$inviterCharacterId) {
            return response()->json(['message' => 'У вас нет персонажа в этой гильдии.'], 403);
        }
        $application = ($this->createInvitationAction)($guild, $characterId, (int) $inviterCharacterId);
        $application->load(['character', 'invitedByCharacter']);
        ($this->createInvitationNotificationAction)($application);

        return (new GuildApplicationResource($application))->response()->setStatusCode(201);
    }

    /**
     * Принять заявку — персонаж становится участником гильдии. Право «Подтверждение или отклонение заявок».
     * Для приглашений (status=invitation) принять может владелец персонажа.
     */
    public function approve(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }
        $application = ($this->approveAction)($request->user(), $guild, $application);
        $application->load('character');
        ($this->createApprovedNotificationAction)($application);

        return response()->json(new GuildApplicationResource($application));
    }

    /**
     * Отклонить заявку. Право «Подтверждение или отклонение заявок».
     */
    public function reject(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }
        $application = ($this->rejectAction)($request->user(), $guild, $application);
        $application->load('character');
        ($this->createRejectedNotificationAction)($application);

        return response()->json(new GuildApplicationResource($application));
    }

    /**
     * Просмотр заявки пользователем, который её подал (или приглашённым).
     */
    public function showForOwner(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }

        $user = $request->user();
        $application->loadMissing(['character', 'invitedByCharacter']);
        $character = $application->character;

        if (!$user || !$character || (int) $character->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }

        $application->load(['character.gameClasses', 'character.game', 'guild.applicationFormFields']);

        return response()->json(new GuildApplicationResource($application));
    }

    /**
     * Принять приглашение в гильдию (владелец персонажа).
     */
    public function acceptInvitation(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }
        $application->loadMissing('character');
        if ($application->status !== 'invitation' || !$application->character || (int) $application->character->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Заявка не найдена или приглашение уже рассмотрено.'], 404);
        }
        $application = ($this->approveAction)($request->user(), $guild, $application);
        $application->load('character');
        ($this->createApprovedNotificationAction)($application);
        return response()->json(new GuildApplicationResource($application));
    }

    /**
     * Отклонить приглашение в гильдию (владелец персонажа).
     */
    public function declineInvitation(Request $request, Guild $guild, GuildApplication $application): JsonResponse
    {
        if ($application->guild_id !== $guild->id) {
            return response()->json(['message' => 'Заявка не найдена.'], 404);
        }
        $application->loadMissing('character');
        if ($application->status !== 'invitation' || !$application->character || (int) $application->character->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Заявка не найдена или приглашение уже рассмотрено.'], 404);
        }
        $application = ($this->rejectAction)($request->user(), $guild, $application);
        $application->load('character');
        ($this->createRejectedNotificationAction)($application);
        return response()->json(new GuildApplicationResource($application));
    }
}
