<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guild\StoreGuildApplicationFormFieldRequest;
use App\Http\Requests\Guild\UpdateGuildApplicationFormFieldRequest;
use App\Http\Resources\Guild\GuildApplicationFormFieldResource;
use Domains\Guild\Actions\CreateGuildApplicationFormFieldAction;
use Domains\Guild\Actions\DeleteGuildApplicationFormFieldAction;
use Domains\Guild\Actions\UpdateGuildApplicationFormFieldAction;
use Domains\Guild\Models\Guild;
use Illuminate\Http\JsonResponse;

class GuildApplicationFormFieldController extends Controller
{
    public function __construct(
        private CreateGuildApplicationFormFieldAction $createAction,
        private UpdateGuildApplicationFormFieldAction $updateAction,
        private DeleteGuildApplicationFormFieldAction $deleteAction
    ) {}

    public function store(StoreGuildApplicationFormFieldRequest $request, Guild $guild): JsonResponse
    {
        $data = $request->validated();
        $field = ($this->createAction)($guild, $data);
        return (new GuildApplicationFormFieldResource($field))->response()->setStatusCode(201);
    }

    public function update(UpdateGuildApplicationFormFieldRequest $request, Guild $guild, int $form_field): JsonResponse
    {
        $field = $guild->applicationFormFields()->findOrFail($form_field);
        $field = ($this->updateAction)($field, $request->validated());
        return response()->json(new GuildApplicationFormFieldResource($field));
    }

    public function destroy(Guild $guild, int $form_field): JsonResponse
    {
        $field = $guild->applicationFormFields()->findOrFail($form_field);
        ($this->deleteAction)($field);
        return response()->json(['message' => 'Поле удалено.']);
    }
}
