<?php

namespace Domains\Game\Jobs;

use Domains\Character\Models\Character;
use Domains\ConstantParty\Models\ConstantParty;
use Domains\Game\Models\Server;
use Domains\Game\Models\ServerMerge;
use Domains\Guild\Models\Guild;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessServerMerge implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $serverMergeId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        do {
            $shouldContinue = $this->processNextBatch();
        } while ($shouldContinue && config('queue.default') === 'sync');

        if ($shouldContinue) {
            self::dispatch($this->serverMergeId)->afterCommit();
        }
    }

    public function failed(Throwable $exception): void
    {
        ServerMerge::query()
            ->whereKey($this->serverMergeId)
            ->whereNotIn('status', [ServerMerge::STATUS_COMPLETED])
            ->update([
                'status' => ServerMerge::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 65000),
                'failed_at' => now(),
            ]);
    }

    private function processNextBatch(): bool
    {
        return DB::transaction(function (): bool {
            $merge = ServerMerge::query()->lockForUpdate()->find($this->serverMergeId);

            if (! $merge || in_array($merge->status, [
                ServerMerge::STATUS_COMPLETED,
                ServerMerge::STATUS_FAILED,
            ], true)) {
                return false;
            }

            if ($merge->status === ServerMerge::STATUS_PENDING) {
                $merge->update([
                    'status' => ServerMerge::STATUS_RUNNING,
                    'started_at' => $merge->started_at ?? now(),
                    'error_message' => null,
                    'failed_at' => null,
                ]);
            }

            return match ($merge->current_stage) {
                ServerMerge::STAGE_CHARACTERS => $this->processModelStage(
                    $merge,
                    Character::class,
                    ServerMerge::STAGE_CHARACTERS,
                    ServerMerge::STAGE_GUILDS,
                ),
                ServerMerge::STAGE_GUILDS => $this->processModelStage(
                    $merge,
                    Guild::class,
                    ServerMerge::STAGE_GUILDS,
                    ServerMerge::STAGE_CONSTANT_PARTIES,
                ),
                ServerMerge::STAGE_CONSTANT_PARTIES => $this->processModelStage(
                    $merge,
                    ConstantParty::class,
                    ServerMerge::STAGE_CONSTANT_PARTIES,
                    ServerMerge::STAGE_SERVER_GROUPS,
                ),
                ServerMerge::STAGE_SERVER_GROUPS => $this->processServerGroups($merge),
                ServerMerge::STAGE_FINALIZING => $this->finalize($merge),
                default => throw new \RuntimeException('Неизвестный этап объединения серверов.'),
            };
        }, 3);
    }

    /**
     * @param  class-string<Character|Guild|ConstantParty>  $modelClass
     */
    private function processModelStage(
        ServerMerge $merge,
        string $modelClass,
        string $stage,
        string $nextStage,
    ): bool {
        $sourceIds = $this->sourceIds($merge);
        $recordIds = $modelClass::query()
            ->whereIn('server_id', $sourceIds)
            ->orderBy('id')
            ->limit($this->chunkSize())
            ->pluck('id');

        if ($recordIds->isEmpty()) {
            $merge->update(['current_stage' => $nextStage]);

            return true;
        }

        $affected = $modelClass::query()
            ->whereIn('id', $recordIds)
            ->whereIn('server_id', $sourceIds)
            ->update(['server_id' => $merge->target_server_id]);

        $this->recordProgress($merge, $stage, $affected);

        return true;
    }

    private function processServerGroups(ServerMerge $merge): bool
    {
        $sourceIds = $this->sourceIds($merge);
        $groupIds = DB::table('server_group_server')
            ->whereIn('server_id', $sourceIds)
            ->select('server_group_id')
            ->distinct()
            ->limit($this->chunkSize())
            ->pluck('server_group_id');

        if ($groupIds->isEmpty()) {
            $merge->update(['current_stage' => ServerMerge::STAGE_FINALIZING]);

            return true;
        }

        DB::table('server_group_server')->insertOrIgnore(
            $groupIds->map(fn (int $groupId): array => [
                'server_group_id' => $groupId,
                'server_id' => $merge->target_server_id,
            ])->all(),
        );

        $deleted = DB::table('server_group_server')
            ->whereIn('server_id', $sourceIds)
            ->whereIn('server_group_id', $groupIds)
            ->delete();

        $this->recordProgress($merge, ServerMerge::STAGE_SERVER_GROUPS, $deleted);

        return true;
    }

    private function finalize(ServerMerge $merge): bool
    {
        $sourceIds = $this->sourceIds($merge);
        $remainingStage = $this->findRemainingStage($sourceIds);

        if ($remainingStage !== null) {
            $merge->update(['current_stage' => $remainingStage]);

            return true;
        }

        Server::query()
            ->whereIn('id', array_merge([$merge->target_server_id], $sourceIds))
            ->lockForUpdate()
            ->get();

        Server::query()
            ->whereIn('id', $sourceIds)
            ->update([
                'merged_into_server_id' => $merge->target_server_id,
                'is_active' => false,
                'is_merging' => false,
            ]);

        Server::query()
            ->whereKey($merge->target_server_id)
            ->update(['is_merging' => false]);

        $merge->update([
            'status' => ServerMerge::STATUS_COMPLETED,
            'current_stage' => null,
            'processed_records' => max($merge->processed_records, $merge->total_records),
            'error_message' => null,
            'finished_at' => now(),
            'failed_at' => null,
        ]);

        return false;
    }

    /**
     * @param  array<int, int>  $sourceIds
     */
    private function findRemainingStage(array $sourceIds): ?string
    {
        if (Character::query()->whereIn('server_id', $sourceIds)->exists()) {
            return ServerMerge::STAGE_CHARACTERS;
        }

        if (Guild::query()->whereIn('server_id', $sourceIds)->exists()) {
            return ServerMerge::STAGE_GUILDS;
        }

        if (ConstantParty::query()->whereIn('server_id', $sourceIds)->exists()) {
            return ServerMerge::STAGE_CONSTANT_PARTIES;
        }

        if (DB::table('server_group_server')->whereIn('server_id', $sourceIds)->exists()) {
            return ServerMerge::STAGE_SERVER_GROUPS;
        }

        return null;
    }

    private function recordProgress(ServerMerge $merge, string $stage, int $processed): void
    {
        $progress = $merge->progress ?? [];
        $stageProgress = $progress[$stage] ?? ['total' => 0, 'processed' => 0];
        $stageProgress['processed'] = min(
            (int) $stageProgress['total'],
            (int) $stageProgress['processed'] + $processed,
        );
        $progress[$stage] = $stageProgress;

        $merge->update([
            'processed_records' => $merge->processed_records + $processed,
            'progress' => $progress,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function sourceIds(ServerMerge $merge): array
    {
        return array_map('intval', $merge->source_server_ids);
    }

    private function chunkSize(): int
    {
        return max(1, (int) config('server_merge.chunk_size', 1000));
    }
}
