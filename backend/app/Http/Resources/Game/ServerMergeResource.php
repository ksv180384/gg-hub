<?php

namespace App\Http\Resources\Game;

use Domains\Game\Models\ServerMerge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServerMerge */
class ServerMergeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $percent = $this->status === ServerMerge::STATUS_COMPLETED
            ? 100
            : ($this->total_records > 0
                ? min(99, (int) floor(($this->processed_records / $this->total_records) * 100))
                : 0);

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'localization_id' => $this->localization_id,
            'target_server_id' => $this->target_server_id,
            'source_server_ids' => $this->source_server_ids,
            'status' => $this->status,
            'current_stage' => $this->current_stage,
            'total_records' => $this->total_records,
            'processed_records' => $this->processed_records,
            'progress_percent' => $percent,
            'progress' => $this->progress,
            'error_message' => $this->error_message,
            'can_resume' => $this->status === ServerMerge::STATUS_FAILED,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
