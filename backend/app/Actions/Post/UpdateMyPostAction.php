<?php

namespace App\Actions\Post;

use App\Actions\Notification\CreatePostPendingGuildModerationNotificationAction;
use App\Actions\Notification\SendPostOrCommentNotificationAction;
use App\Services\Notifications\GuildLinkBuilder;
use App\Http\Requests\Post\UpdatePostRequest;
use Domains\Guild\Models\Guild;
use Domains\Post\Actions\ApplyPostModerationRulesAction;
use Domains\Post\Actions\BuildPostDataFromRequestAction;
use Domains\Post\Actions\BuildPostPreviewAction;
use Domains\Post\Actions\FixPostBodyHtmlAction;
use Domains\Post\Actions\SyncPostBodyImagesAction;
use Domains\Post\Actions\UpdatePostAction;
use Domains\Post\Enums\PostStatus;
use Domains\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Purify\Facades\Purify;

class UpdateMyPostAction
{
    public function __construct(
        private UpdatePostAction $updatePostAction,
        private BuildPostDataFromRequestAction $buildPostDataFromRequestAction,
        private ApplyPostModerationRulesAction $applyPostModerationRulesAction,
        private SyncPostBodyImagesAction $syncPostBodyImagesAction,
        private CreatePostPendingGuildModerationNotificationAction $createPostPendingGuildModerationNotificationAction,
        private SendPostOrCommentNotificationAction $sendPostOrCommentNotificationAction,
        private GuildLinkBuilder $linkBuilder,
    ) {}

    public function __invoke(UpdatePostRequest $request, Post $post): Post
    {
        $user = $request->user();

        $previousStatusGuild = $post->status_guild;
        $rawBody = (string) ($request->input('body') ?? '');

        return DB::transaction(function () use ($request, $user, $post, $previousStatusGuild, $rawBody) {
            $data = ($this->buildPostDataFromRequestAction)($request);

            $result = ($this->applyPostModerationRulesAction)($data, $user);
            $data = $result['data'];

            // body/preview обновляем отдельным шагом после синка картинок
            unset($data['body'], $data['preview']);

            // Заблокированный статус меняет только модератор/админ; автор не может его снять при редактировании
            if ($post->status_global === PostStatus::Blocked->value) {
                $data['status_global'] = PostStatus::Blocked->value;
            }
            if ($post->status_guild === PostStatus::Blocked->value) {
                $data['status_guild'] = PostStatus::Blocked->value;
            }

            // Статус на модерации снять при редактировании нельзя — только решение модератора
            if ($post->status_global === PostStatus::Pending->value) {
                $data['status_global'] = PostStatus::Pending->value;
            }
            if ($post->status_guild === PostStatus::Pending->value) {
                $data['status_guild'] = PostStatus::Pending->value;
            }

            $post = ($this->updatePostAction)($post, $data);

            $createdFiles = [];

            try {
                $fixedBody = app(FixPostBodyHtmlAction::class)($rawBody);
                $sync = ($this->syncPostBodyImagesAction)($post, $fixedBody);
                $createdFiles = $sync['created'] ?? [];

                $body = Purify::config('guild_rich_text')->clean($sync['html'] ?? '');
                $preview = app(BuildPostPreviewAction::class)($body);
                $post->forceFill(['body' => $body, 'preview' => $preview])->save();
            } catch (\Throwable $e) {
                foreach ($createdFiles as $path) {
                    if (is_string($path) && $path !== '') {
                        Storage::disk('public')->delete($path);
                    }
                }
                throw $e;
            }

            $this->sendPostOrCommentNotificationAction->postUpdated($post);

            $wasAlreadyPending = $previousStatusGuild === 'pending';
            if (($result['notify_guild_id'] ?? null) !== null && !$wasAlreadyPending) {
                $guild = Guild::query()->find($result['notify_guild_id']);
                if ($guild) {
                    $link = $this->linkBuilder->postPath($guild, (int) $post->id);
                    ($this->createPostPendingGuildModerationNotificationAction)($guild, $post, $link);
                }
            }

            return $post;
        });
    }
}

