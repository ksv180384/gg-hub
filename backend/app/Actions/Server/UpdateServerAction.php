<?php

namespace App\Actions\Server;

use App\Models\Server;

class UpdateServerAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function __invoke(Server $server, array $data): Server
    {
        $server->update($data);
        return $server;
    }
}
