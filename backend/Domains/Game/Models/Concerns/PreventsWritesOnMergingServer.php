<?php

namespace Domains\Game\Models\Concerns;

use Domains\Game\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait PreventsWritesOnMergingServer
{
    protected static function bootPreventsWritesOnMergingServer(): void
    {
        static::saving(function (Model $model): void {
            if ($model->exists && ! $model->isDirty('server_id')) {
                return;
            }

            static::ensureServerIsNotMerging($model->getAttribute('server_id'));
        });

        static::deleting(function (Model $model): void {
            static::ensureServerIsNotMerging($model->getAttribute('server_id'));
        });
    }

    private static function ensureServerIsNotMerging(mixed $serverId): void
    {
        if (! $serverId) {
            return;
        }

        if (Server::query()->whereKey($serverId)->where('is_merging', true)->exists()) {
            throw ValidationException::withMessages([
                'server_id' => 'Сервер находится в процессе объединения. Изменение временно недоступно.',
            ]);
        }
    }
}
