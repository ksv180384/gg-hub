<?php

namespace App\Logging\NotificationHub;

use Monolog\Logger;

class NotificationHubLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('notification-hub');
        $logger->pushHandler(new NotificationHubLogger($config));

        return $logger;
    }
}
