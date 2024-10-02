<?php

namespace App\Jobs;

use App\Services\Skill\Tl\SkillService;
use App\Services\SocketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TransferSkillToSiteJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $skillsParserItem,
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SkillService $skillService, SocketService $socketService): void
    {
        try {
            $skillService->transferToSite($this->skillsParserItem);
            $socketService->sendAction('skill-parser-to-site', [
                'name' => 'Перенос умений с сервера парсинга на сайт',
                'info' => $this->skillsParserItem['name'],
            ]);
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage());
        }
    }
}
