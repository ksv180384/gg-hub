<?php

namespace App\Actions\Server;

use App\Models\Server;

class DeleteServerAction
{
    public function __invoke(Server $server): void
    {
        $server->delete();
    }
}
