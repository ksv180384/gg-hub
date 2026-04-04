<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('post_comments')) {
            return;
        }

        if (Schema::hasColumn('post_comments', 'character_id')) {
            return;
        }

        Schema::table('post_comments', function (Blueprint $table) {
            $table->foreignId('character_id')->after('user_id')->nullable()->constrained('characters')->nullOnDelete();
        });

        // Заполнить character_id: для каждой записи взять первого персонажа пользователя в гильдии поста
        $comments = \DB::table('post_comments')->whereNull('character_id')->get();
        foreach ($comments as $comment) {
            $post = \DB::table('posts')->find($comment->post_id);
            if (! $post || ! $post->guild_id) {
                continue;
            }
            $member = \DB::table('guild_members')
                ->join('characters', 'guild_members.character_id', '=', 'characters.id')
                ->where('guild_members.guild_id', $post->guild_id)
                ->where('characters.user_id', $comment->user_id)
                ->select('characters.id as character_id')
                ->orderBy('guild_members.joined_at')
                ->first();
            if ($member) {
                \DB::table('post_comments')->where('id', $comment->id)->update(['character_id' => $member->character_id]);
            }
        }

    }

    public function down(): void
    {
        if (Schema::hasColumn('post_comments', 'character_id')) {
            Schema::table('post_comments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('character_id');
            });
        }
    }
};
