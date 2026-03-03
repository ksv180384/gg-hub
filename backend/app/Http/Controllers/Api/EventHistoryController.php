<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventHistory\StoreEventHistoryRequest;
use App\Http\Requests\EventHistory\UpdateEventHistoryRequest;
use App\Http\Resources\EventHistory\EventHistoryResource;
use Domains\Event\Actions\CreateEventHistoryAction;
use Domains\Event\Actions\DeleteEventHistoryAction;
use Domains\Event\Actions\GetEventHistoryAction;
use Domains\Event\Actions\ListGuildEventHistoriesAction;
use Domains\Event\Actions\UpdateEventHistoryAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventHistoryController extends Controller
{
    public function __construct(
        private readonly ListGuildEventHistoriesAction $listGuildEventHistoriesAction,
        private readonly GetEventHistoryAction $getEventHistoryAction,
        private readonly CreateEventHistoryAction $createEventHistoryAction,
        private readonly UpdateEventHistoryAction $updateEventHistoryAction,
        private readonly DeleteEventHistoryAction $deleteEventHistoryAction,
    ) {
    }

    /**
     * История событий гильдии.
     */
    public function index(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $perPage = $request->query('per_page');

        $items = ($this->listGuildEventHistoriesAction)($guild, [
            'per_page' => is_numeric($perPage) ? (int) $perPage : null,
        ]);

        return EventHistoryResource::collection($items);
    }

    /**
     * Одно событие из истории.
     */
    public function show(Guild $guild, int $eventHistory): JsonResponse
    {
        $model = ($this->getEventHistoryAction)($guild, $eventHistory);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        return response()->json(new EventHistoryResource($model));
    }

    public function store(StoreEventHistoryRequest $request, Guild $guild): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'guild_id' => $guild->id,
        ]);

        $eventHistory = ($this->createEventHistoryAction)($data);

        return (new EventHistoryResource($eventHistory))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEventHistoryRequest $request, Guild $guild, int $eventHistory): JsonResponse
    {
        $model = ($this->getEventHistoryAction)($guild, $eventHistory);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        $updated = ($this->updateEventHistoryAction)($model, $request->validated());

        return response()->json(new EventHistoryResource($updated));
    }

    public function destroy(Guild $guild, int $eventHistory): Response
    {
        $model = ($this->getEventHistoryAction)($guild, $eventHistory);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        ($this->deleteEventHistoryAction)($model);

        return response()->noContent();
    }
}

