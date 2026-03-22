<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Poll\AdminDeletePollRequest;
use App\Http\Resources\Poll\AdminPollResource;
use Domains\Poll\Actions\AdminDeletePollAction;
use Domains\Poll\Actions\ListAdminPollsAction;
use Domains\Poll\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Голосования в админке. Просмотр — prosmatirivat-golosovaniia, удаление — udaliat-golosovanie.
 */
class AdminPollController extends Controller
{
    public function __construct(
        private ListAdminPollsAction $listAdminPollsAction,
        private AdminDeletePollAction $adminDeletePollAction
    ) {}

    /**
     * Список всех голосований.
     * Query: per_page, guild_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $guildId = $request->query('guild_id');
        $guildId = is_numeric($guildId) ? (int) $guildId : null;

        $paginator = ($this->listAdminPollsAction)($perPage, $guildId);

        return AdminPollResource::collection($paginator);
    }

    /**
     * Удалить голосование (с причиной и уведомлением автору).
     */
    public function destroy(AdminDeletePollRequest $request, Poll $poll): JsonResponse
    {
        ($this->adminDeletePollAction)($poll, $request->validated('reason', ''));

        return response()->json(['message' => 'Голосование удалено.']);
    }
}
