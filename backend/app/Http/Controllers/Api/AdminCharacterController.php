<?php

namespace App\Http\Controllers\Api;

use App\Filters\AdminCharacterFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Character\AdminCharacterIndexRequest;
use App\Http\Resources\Character\AdminCharacterResource;
use Domains\Character\Actions\ListAdminCharactersAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCharacterController extends Controller
{
    public function __construct(
        private ListAdminCharactersAction $listAdminCharactersAction,
    ) {}

    public function index(AdminCharacterIndexRequest $request): AnonymousResourceCollection
    {
        $characters = ($this->listAdminCharactersAction)(
            new AdminCharacterFilter($request),
            $request->validated(),
        );

        return AdminCharacterResource::collection($characters);
    }
}
