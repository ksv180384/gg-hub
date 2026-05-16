<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventHistory\StoreEventHistoryTitleRequest;
use App\Http\Requests\EventHistory\UpdateEventHistoryTitleRequest;
use App\Http\Resources\EventHistory\EventHistoryTitleResource;
use Domains\Event\Actions\CreateEventHistoryTitleAction;
use Domains\Event\Actions\DeleteEventHistoryTitleAction;
use Domains\Event\Actions\ListEventHistoryTitlesAction;
use Domains\Event\Actions\UpdateEventHistoryTitleAction;
use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class EventHistoryTitleController extends Controller
{
    public function __construct(
        private readonly ListEventHistoryTitlesAction $listEventHistoryTitlesAction,
        private readonly CreateEventHistoryTitleAction $createEventHistoryTitleAction,
        private readonly DeleteEventHistoryTitleAction $deleteEventHistoryTitleAction,
        private readonly UpdateEventHistoryTitleAction $updateEventHistoryTitleAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->query('query');
        $limit = $request->query('limit');

        $titles = ($this->listEventHistoryTitlesAction)([
            'query' => is_string($query) ? $query : null,
            'limit' => is_numeric($limit) ? (int) $limit : 10,
        ]);

        return EventHistoryTitleResource::collection($titles);
    }

    public function store(StoreEventHistoryTitleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $title = ($this->createEventHistoryTitleAction)(
            $validated['name'],
            $validated['dkp_base_points'] ?? null,
            (bool) ($validated['distribute_dkp_to_participants'] ?? false),
        );

        return (new EventHistoryTitleResource($title->loadCount('histories')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateEventHistoryTitleRequest $request,
        EventHistoryTitle $eventHistoryTitle,
    ): EventHistoryTitleResource {
        $validated = $request->validated();

        $title = ($this->updateEventHistoryTitleAction)($eventHistoryTitle, $validated);

        return new EventHistoryTitleResource($title->loadCount('histories'));
    }

    public function destroy(EventHistoryTitle $eventHistoryTitle): JsonResponse
    {
        try {
            ($this->deleteEventHistoryTitleAction)($eventHistoryTitle);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json()->setStatusCode(204);
    }
}
