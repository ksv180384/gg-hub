<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Domains\Event\Actions\ListEventHistoryTitlesAction;
use Domains\Event\Actions\DeleteEventHistoryTitleAction;
use Domains\Event\Actions\UpdateEventHistoryTitleAction;
use Domains\Event\Models\EventHistoryTitle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventHistoryTitleController extends Controller
{
    public function __construct(
        private readonly ListEventHistoryTitlesAction $listEventHistoryTitlesAction,
        private readonly DeleteEventHistoryTitleAction $deleteEventHistoryTitleAction,
        private readonly UpdateEventHistoryTitleAction $updateEventHistoryTitleAction,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query('query');
        $limit = $request->query('limit');

        $titles = ($this->listEventHistoryTitlesAction)([
            'query' => is_string($query) ? $query : null,
            'limit' => is_numeric($limit) ? (int) $limit : 10,
        ]);

        return response()->json([
            'data' => $titles->map(fn ($title) => [
                'id' => $title->id,
                'name' => $title->name,
            ]),
        ]);
    }

    /**
     * Обновление названия (только текста), используется в выпадающем списке на фронтенде.
     */
    public function update(Request $request, EventHistoryTitle $eventHistoryTitle): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $title = ($this->updateEventHistoryTitleAction)($eventHistoryTitle, $validated['name']);

        return response()->json([
            'id' => $title->id,
            'name' => $title->name,
        ]);
    }

    /**
     * Удаление названия, если оно не использовалось ни в одном событии.
     */
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

