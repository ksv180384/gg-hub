<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\GuildActivity\ListGuildActivityLogsAction;
use App\Filters\GuildActivityLogFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuildActivity\ListGuildActivityLogsRequest;
use App\Http\Resources\GuildActivity\GuildActivityLogResource;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;

class GuildActivityLogController extends Controller
{
    public function __construct(
        private ListGuildActivityLogsAction $listGuildActivityLogsAction,
    ) {}

    public function index(ListGuildActivityLogsRequest $request, Guild $guild): JsonResponse
    {
        $logs = ($this->listGuildActivityLogsAction)(
            $guild,
            new GuildActivityLogFilter($request),
        );

        return response()->json([
            'data' => GuildActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
