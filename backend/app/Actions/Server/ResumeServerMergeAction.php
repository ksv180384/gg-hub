<?php

namespace App\Actions\Server;

use Domains\Game\Jobs\ProcessServerMerge;
use Domains\Game\Models\Server;
use Domains\Game\Models\ServerMerge;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResumeServerMergeAction
{
    public function __invoke(ServerMerge $serverMerge): ServerMerge
    {
        $merge = DB::transaction(function () use ($serverMerge): ServerMerge {
            $merge = ServerMerge::query()->lockForUpdate()->findOrFail($serverMerge->id);

            if ($merge->status !== ServerMerge::STATUS_FAILED) {
                throw new HttpException(422, 'Продолжить можно только объединение со статусом ошибки.');
            }

            $sourceIds = array_map('intval', $merge->source_server_ids);

            Server::query()
                ->whereIn('id', array_merge([$merge->target_server_id], $sourceIds))
                ->update(['is_merging' => true]);

            Server::query()
                ->whereIn('id', $sourceIds)
                ->update(['is_active' => false]);

            $merge->update([
                'status' => ServerMerge::STATUS_PENDING,
                'error_message' => null,
                'failed_at' => null,
            ]);

            return $merge;
        });

        ProcessServerMerge::dispatch($merge->id);

        return $merge->fresh();
    }
}
