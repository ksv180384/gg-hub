<?php

namespace App\Models;

use App\Services\GameImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'max_classes_per_character',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_classes_per_character' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Game $game): void {
            $game->deleteImageFiles();
        });
    }

    /**
     * Удаляет с диска файлы изображений игры.
     * Новый формат: games/{id}/images/ — удаляется вся папка.
     * Старый формат: games/xxx.jpg — удаляются только файлы.
     */
    public function deleteImageFiles(): void
    {
        if (!$this->image) {
            return;
        }
        $disk = Storage::disk('public');
        if (\str_contains($this->image, '/images/')) {
            $disk->deleteDirectory(\dirname($this->image));
        } else {
            $disk->delete($this->image);
            $disk->delete(GameImageService::previewPath($this->image));
            $disk->delete(GameImageService::thumbPath($this->image));
        }
    }

    public function localizations(): HasMany
    {
        return $this->hasMany(Localization::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    public function gameClasses(): HasMany
    {
        return $this->hasMany(GameClass::class);
    }
}
