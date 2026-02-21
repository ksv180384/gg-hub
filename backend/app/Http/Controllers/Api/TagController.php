<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Http\Resources\Tag\TagResource;
use Domains\Tag\Actions\CreateTagAction;
use Domains\Tag\Actions\DeleteTagAction;
use Domains\Tag\Actions\ListTagsAction;
use Domains\Tag\Actions\UpdateTagAction;
use Domains\Tag\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    private const PERMISSION_ADMIN = 'admnistrirovanie';

    public function __construct(
        private ListTagsAction $listTagsAction,
        private CreateTagAction $createTagAction,
        private UpdateTagAction $updateTagAction,
        private DeleteTagAction $deleteTagAction
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $includeHidden = $request->boolean('include_hidden')
            && $request->user()
            && in_array(self::PERMISSION_ADMIN, $request->user()->getAllPermissionSlugs(), true);
        $tags = ($this->listTagsAction)($includeHidden);
        return TagResource::collection($tags);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = ($this->createTagAction)($request->validated());
        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        ($this->updateTagAction)($tag, $request->validated());
        return new TagResource($tag->fresh());
    }

    public function destroy(Tag $tag): Response
    {
        ($this->deleteTagAction)($tag);
        return response()->noContent();
    }
}
