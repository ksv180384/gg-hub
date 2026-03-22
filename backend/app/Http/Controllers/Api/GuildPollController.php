<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Poll\StorePollRequest;
use App\Http\Requests\Poll\UpdatePollRequest;
use App\Http\Requests\Poll\VotePollRequest;
use App\Http\Requests\Poll\WithdrawVotePollRequest;
use App\Http\Resources\Poll\PollResource;
use Domains\Guild\Models\Guild;
use Domains\Poll\Actions\ClosePollAction;
use Domains\Poll\Actions\CreatePollAction;
use Domains\Poll\Actions\DeletePollAction;
use Domains\Poll\Actions\GetPollAction;
use Domains\Poll\Actions\ListGuildPollsAction;
use Domains\Poll\Actions\ResetPollAction;
use Domains\Poll\Actions\UpdatePollAction;
use Domains\Poll\Actions\VotePollAction;
use Domains\Poll\Actions\WithdrawPollVoteAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Голосования гильдии. Доступ только участникам гильдии (middleware guild.member).
 */
class GuildPollController extends Controller
{
    public function __construct(
        private ListGuildPollsAction $listGuildPollsAction,
        private GetPollAction $getPollAction,
        private CreatePollAction $createPollAction,
        private UpdatePollAction $updatePollAction,
        private DeletePollAction $deletePollAction,
        private ClosePollAction $closePollAction,
        private ResetPollAction $resetPollAction,
        private VotePollAction $votePollAction,
        private WithdrawPollVoteAction $withdrawPollVoteAction
    ) {}

    public function index(Guild $guild): AnonymousResourceCollection
    {
        $polls = ($this->listGuildPollsAction)($guild);

        return PollResource::collection($polls);
    }

    public function show(Guild $guild, int $poll): JsonResponse
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        return response()->json(new PollResource($model));
    }

    public function store(StorePollRequest $request, Guild $guild): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'guild_id' => $guild->id,
            'created_by' => $request->user()?->getKey(),
            'created_by_character_id' => $request->validated('created_by_character_id'),
        ]);

        $poll = ($this->createPollAction)($data);

        return (new PollResource($poll))->response()->setStatusCode(201);
    }

    public function update(UpdatePollRequest $request, Guild $guild, int $poll): JsonResponse
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        $updated = ($this->updatePollAction)($model, $request->validated());

        return response()->json(new PollResource($updated));
    }

    public function destroy(Guild $guild, int $poll): Response
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        ($this->deletePollAction)($model);

        return response()->noContent();
    }

    public function close(Guild $guild, int $poll): JsonResponse
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        $updated = ($this->closePollAction)($model);

        return response()->json(new PollResource($updated));
    }

    public function reset(Guild $guild, int $poll): JsonResponse
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        $updated = ($this->resetPollAction)($model);

        return response()->json(new PollResource($updated));
    }

    public function vote(VotePollRequest $request, Guild $guild, int $poll): Response
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        ($this->votePollAction)($model, $request->validated('character_id'), $request->validated('option_id'));

        return response()->noContent();
    }

    public function withdrawVote(WithdrawVotePollRequest $request, Guild $guild, int $poll): Response
    {
        try {
            $model = ($this->getPollAction)($guild, $poll);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new NotFoundHttpException('Голосование не найдено.');
        }

        ($this->withdrawPollVoteAction)($model, $request->validated('character_id'));

        return response()->noContent();
    }
}
