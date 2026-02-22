<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
    private const PERMISSION_TAG_EDIT = 'redaktirovat-teg';
    private const PERMISSION_TAG_HIDE = 'skryvat-teg';
    private const PERMISSION_TAG_DELETE = 'udaliat-teg';

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
        $data = $request->validated();
        $user = $request->user();
        $data['created_by_user_id'] = $user?->id;
        $tag = ($this->createTagAction)($data);
        $tag->load('createdBy');
        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse|TagResource
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }
        $slugs = $user->getAllPermissionSlugs();
        $validated = $request->validated();
        if (array_key_exists('is_hidden', $validated) && !in_array(self::PERMISSION_TAG_HIDE, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для скрытия/показа тега.'], 403);
        }
        if ((array_key_exists('name', $validated) || array_key_exists('slug', $validated))
            && !in_array(self::PERMISSION_TAG_EDIT, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для редактирования тега.'], 403);
        }
        ($this->updateTagAction)($tag, $validated);
        return new TagResource($tag->fresh());
    }

    public function destroy(Tag $tag): JsonResponse|Response
    {
        $user = request()->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Необходима авторизация.'], 401);
        }
        $slugs = $user->getAllPermissionSlugs();
        if (!in_array(self::PERMISSION_TAG_DELETE, $slugs, true)) {
            return response()->json(['message' => 'Недостаточно прав для удаления тега.'], 403);
        }
        ($this->deleteTagAction)($tag);
        return response()->noContent();
    }
}
