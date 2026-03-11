<?php

use Domains\Post\Actions\BuildPostPreviewAction;
use Domains\Post\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('posts', 'preview')) {
            return;
        }

        $buildPreview = app(BuildPostPreviewAction::class);

        Post::query()
            ->where(function ($q) {
                $q->whereNull('preview')->orWhere('preview', '');
            })
            ->chunkById(100, function ($posts) use ($buildPreview) {
                foreach ($posts as $post) {
                    $body = $post->body ?? '';
                    if ($body === '') {
                        continue;
                    }
                    $preview = $buildPreview($body);
                    DB::table('posts')->where('id', $post->id)->update(['preview' => $preview]);
                }
            });
    }

    public function down(): void
    {
        // Не обращаем: превью остаётся в БД
    }
};
