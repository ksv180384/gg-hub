<?php

namespace App\Console\Commands;

use App\GuildActivityLog;
use Illuminate\Console\Command;

class PruneExpiredGuildActivityLogsCommand extends Command
{
    protected $signature = 'guild-activity:prune-expired';

    protected $description = 'Delete guild activity logs that have been stored for three months.';

    public function handle(): int
    {
        $deleted = GuildActivityLog::query()
            ->where('created_at', '<=', now()->subMonthsNoOverflow(3))
            ->delete();

        $this->info("Deleted {$deleted} expired guild activity log(s).");

        return self::SUCCESS;
    }
}
