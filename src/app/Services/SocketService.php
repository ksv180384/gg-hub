<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class SocketService
{

    private string $pathSocketServer;

    public function __construct()
    {
        $this->pathSocketServer = env('SOCKET_SERVER_PATH');
    }

    public function sendAction(string $action, array $data)
    {
        $response = Http::post($this->pathSocketServer . '/send-action', ['action' => $action, 'data' => $data]);


        if (!$response->successful()) {
            throw ValidationException::withMessages(['message' => 'При отправке сокет Экшена.']);
        }
//        $res = $response->json();
    }
}
