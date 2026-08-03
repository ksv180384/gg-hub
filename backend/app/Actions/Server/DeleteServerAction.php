<?php

namespace App\Actions\Server;

use Domains\Game\Models\Server;

class DeleteServerAction
{
    public function __invoke(Server $server): void
    {
        $server->delete();
    }
}
