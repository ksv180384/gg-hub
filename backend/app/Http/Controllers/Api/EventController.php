<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\Event\EventResource;
use Domains\Event\Actions\CreateEventAction;
use Domains\Event\Actions\DeleteEventAction;
use Domains\Event\Actions\GetEventAction;
use Domains\Event\Actions\ListGuildEventsAction;
use Domains\Event\Actions\UpdateEventAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventController extends Controller
{
    public function __construct(
        private ListGuildEventsAction $listGuildEventsAction,
        private GetEventAction $getEventAction,
        private CreateEventAction $createEventAction,
        private UpdateEventAction $updateEventAction,
        private DeleteEventAction $deleteEventAction
    ) {}

    /**
     * События гильдии за период. Query: from (Y-m-d), to (Y-m-d).
     */
    public function index(Request $request, Guild $guild): AnonymousResourceCollection
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $events = ($this->listGuildEventsAction)($guild, [
            'from' => is_string($from) ? $from : null,
            'to' => is_string($to) ? $to : null,
        ]);

        return EventResource::collection($events);
    }

    /**
     * Одно событие гильдии.
     */
    public function show(Guild $guild, int $event): JsonResponse
    {
        $model = ($this->getEventAction)($guild, $event);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        return response()->json(new EventResource($model));
    }

    public function store(StoreEventRequest $request, Guild $guild): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'guild_id' => $guild->id,
            'created_by_character_id' => $request->validated('character_id'),
        ]);
        unset($data['character_id']);
        $event = ($this->createEventAction)($data);
        $event->load('creator:id,name');

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function update(UpdateEventRequest $request, Guild $guild, int $event): JsonResponse
    {
        $model = ($this->getEventAction)($guild, $event);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        $updated = ($this->updateEventAction)($model, $request->validated());

        return response()->json(new EventResource($updated));
    }

    public function destroy(Guild $guild, int $event): Response
    {
        $model = ($this->getEventAction)($guild, $event);
        if ($model === null) {
            throw new NotFoundHttpException('Событие не найдено.');
        }

        ($this->deleteEventAction)($model);

        return response()->noContent();
    }
}
