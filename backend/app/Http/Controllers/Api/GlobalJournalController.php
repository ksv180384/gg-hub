<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostListResource;
use App\Models\Game;
use Domains\Post\Actions\ListGlobalPostsForJournalAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Журнал общих постов (раздел «Общие»).
 */
class GlobalJournalController extends Controller
{
    public function __construct(
        private ListGlobalPostsForJournalAction $listGlobalPostsForJournalAction
    ) {}

    /**
     * Список опубликованных общих постов для игры.
     */
    public function index(Game $game): AnonymousResourceCollection
    {
        $posts = ($this->listGlobalPostsForJournalAction)($game->id);
        $posts->loadMissing(['character', 'character.user', 'user']);

        return PostListResource::collection($posts);
    }
}
