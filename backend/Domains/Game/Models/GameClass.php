<?php

namespace Domains\Game\Models;

use App\Services\GameClassImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GameClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'name',
        'name_ru',
        'slug',
        'image',
    ];

    protected static function booted(): void
    {
        static::deleting(function (GameClass $gameClass): void {
            $gameClass->deleteImageFiles();
        });
    }

    public function deleteImageFiles(): void
    {
        if (! $this->image) {
            return;
        }
        $disk = Storage::disk('public');
        if (str_contains($this->image, '/images/')) {
            $disk->deleteDirectory(dirname($this->image));
        } else {
            $disk->delete($this->image);
            $disk->delete(GameClassImageService::previewPath($this->image));
            $disk->delete(GameClassImageService::thumbPath($this->image));
        }
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
