<?php

namespace App\Services\Telegram;

use App\Services\Telegram\Exceptions\TelegramBotApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotApi
{
    public const HOST = 'https://api.telegram.org/bot';

    public static function sendMessage(string $token, int $chatId, string $text): bool
    {
        if ($token === '' || $chatId === 0) {
            return false;
        }

        try {
            $response = Http::get(self::HOST . $token . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            if ($response->successful() && ($response['ok'] ?? false)) {
                return true;
            }

            Log::error('Telegram Bot API sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Telegram Bot API sendMessage exception', [
                'message' => $e->getMessage(),
            ]);
            report(new TelegramBotApiException($e->getMessage()));

            return false;
        }
    }
}
