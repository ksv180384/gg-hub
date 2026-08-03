<?php

namespace App\Console\Commands;

use Domains\Notification\Models\Notification;
use Illuminate\Console\Command;

class PruneExpiredNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune-expired';

    protected $description = 'Delete in-app notifications that have been stored for three months.';

    public function handle(): int
    {
        $deleted = Notification::query()
            ->where('created_at', '<=', now()->subMonthsNoOverflow(3))
            ->delete();

        $this->info("Deleted {$deleted} expired notification(s).");

        return self::SUCCESS;
    }
}
