<?php

namespace App\Logging\NotificationHub;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Отправляет лог-сообщение в notification-gg-hub по HTTP.
 * Hub в свою очередь пересылает сообщение в Telegram.
 */
class NotificationHubLogger extends AbstractProcessingHandler
{
    private string $url;

    private string $token;

    private int $timeout;

    public function __construct(array $config)
    {
        $level = Level::fromName($config['level'] ?? 'debug');
        parent::__construct($level);

        $this->url = rtrim((string) ($config['url'] ?? ''), '/');
        $this->token = (string) ($config['token'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? 10);

        Log::channel('single')->error('111111111', [
            'url' => $this->url,
            'token' => $this->token,
        ]);
    }

    protected function write(LogRecord $record): void
    {
        Log::channel('single')->error('Notification send', [
            'url' => $this->url,
            'token' => $this->token,
        ]);
        if ($this->url === '' || $this->token === '') {
            return;
        }

        $message = $record->datetime->format('d.m.Y H:i:s') . ' ' . $record->message;

        try {
            Http::withHeaders([
                'X-Notification-Hub-Token' => $this->token,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->url . '/api/notifications', [
                    'message' => $message,
                ]);
            Log::channel('single')->error('Notification send', [
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Notification hub request failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
