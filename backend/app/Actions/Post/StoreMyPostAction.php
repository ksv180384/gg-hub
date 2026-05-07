<?php

namespace App\Actions\Post;

use App\Actions\Notification\CreatePostPendingGuildModerationNotificationAction;
use App\Actions\Notification\SendPostOrCommentNotificationAction;
use App\Http\Requests\Post\StorePostRequest;
use Domains\Guild\Models\Guild;
use Domains\Post\Actions\ApplyPostModerationRulesAction;
use Domains\Post\Actions\BuildPostDataFromRequestAction;
use Domains\Post\Actions\BuildPostPreviewAction;
use Domains\Post\Actions\CreatePostAction;
use Domains\Post\Actions\FixPostBodyHtmlAction;
use Domains\Post\Actions\SyncPostBodyImagesAction;
use Domains\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Purify\Facades\Purify;

class StoreMyPostAction
{
    public function __construct(
        private CreatePostAction $createPostAction,
        private BuildPostDataFromRequestAction $buildPostDataFromRequestAction,
        private ApplyPostModerationRulesAction $applyPostModerationRulesAction,
        private SyncPostBodyImagesAction $syncPostBodyImagesAction,
        private CreatePostPendingGuildModerationNotificationAction $createPostPendingGuildModerationNotificationAction,
        private SendPostOrCommentNotificationAction $sendPostOrCommentNotificationAction,
    ) {}

    public function __invoke(StorePostRequest $request): Post
    {
        $user = $request->user();

        $rawBody = (string) ($request->input('body') ?? '');

        return DB::transaction(function () use ($request, $user, $rawBody) {
            $data = ($this->buildPostDataFromRequestAction)($request);

            $result = ($this->applyPostModerationRulesAction)($data, $user);
            $data = $result['data'];

            $data['user_id'] = $user->id;
            $data['published_at_global'] = null;

            // В body могут быть base64-картинки — они могут не поместиться в БД.
            // Создаём пост без body/preview, затем синхронизируем изображения и сохраняем итоговый HTML.
            $data['body'] = '';
            $data['preview'] = '';

            $post = ($this->createPostAction)($data);

            try {
                $fixedBody = app(FixPostBodyHtmlAction::class)($rawBody);
                $sync = ($this->syncPostBodyImagesAction)($post, $fixedBody);

                $body = Purify::config('guild_rich_text')->clean($sync['html'] ?? '');
                $preview = app(BuildPostPreviewAction::class)($body);
                $post->forceFill(['body' => $body, 'preview' => $preview])->save();
            } catch (\Throwable $e) {
                // БД транзакция откатится, но файлы в storage — нет.
                // Для нового поста безопасно удалить всё post/{id}.
                Storage::disk('public')->deleteDirectory('post/' . $post->id);
                throw $e;
            }

            $this->sendPostOrCommentNotificationAction->postCreated($post);

            if (($result['notify_guild_id'] ?? null) !== null) {
                $guild = Guild::query()->find($result['notify_guild_id']);
                if ($guild) {
                    $link = '/guilds/' . $guild->id . '/posts/' . $post->id;
                    ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
                }
            }

            return $post;
        });
    }
}

