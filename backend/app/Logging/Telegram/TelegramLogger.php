<?php

namespace App\Logging\Telegram;

use App\Services\Telegram\TelegramBotApi;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class TelegramLogger extends AbstractProcessingHandler
{
    private int $chatId;

    private string $token;

    public function __construct(array $config)
    {
        $level = Level::fromName($config['level'] ?? 'debug');
        parent::__construct($level);

        $this->chatId = (int) ($config['chat_id'] ?? 0);
        $this->token = (string) ($config['token'] ?? '');
    }

    protected function write(LogRecord $record): void
    {
        if ($this->token === '' || $this->chatId === 0) {
            return;
        }

        $message = $record->datetime->format('d.m.Y H:i:s') . ' ' . $record->message;
        TelegramBotApi::sendMessage($this->token, $this->chatId, $message);
    }
}
