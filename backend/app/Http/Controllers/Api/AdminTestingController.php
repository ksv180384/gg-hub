<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminTestingController extends Controller
{
    public function telegram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $channel = (string) config('logging.notifications_channel', 'notification-hub');
        Log::channel($channel)->info($data['message']);

        return response()->json([
            'message' => 'Тестовое сообщение отправлено в Telegram.',
        ]);
    }

    public function email(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        Mail::raw(
            "Это тестовое сообщение для проверки отправки почты.\n\n{$data['message']}",
            function ($mail) use ($data): void {
                $mail
                    ->to($data['email'])
                    ->subject('Тестовое сообщение');
            },
        );

        return response()->json([
            'message' => 'Тестовое сообщение отправлено на email.',
        ]);
    }
}
