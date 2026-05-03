<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            if (!Schema::hasColumn('guilds', 'discord_webhook_url')) {
                $table->string('discord_webhook_url', 255)->nullable()->after('charter_text');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_application_new')) {
                $table->boolean('discord_notify_application_new')->default(false)->after('discord_webhook_url');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_member_joined')) {
                $table->boolean('discord_notify_member_joined')->default(false)->after('discord_notify_application_new');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_member_left')) {
                $table->boolean('discord_notify_member_left')->default(false)->after('discord_notify_member_joined');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_event_starting')) {
                $table->boolean('discord_notify_event_starting')->default(false)->after('discord_notify_member_left');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_poll_started')) {
                $table->boolean('discord_notify_poll_started')->default(false)->after('discord_notify_event_starting');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_role_changed')) {
                $table->boolean('discord_notify_role_changed')->default(false)->after('discord_notify_poll_started');
            }
            if (!Schema::hasColumn('guilds', 'discord_notify_post_published')) {
                $table->boolean('discord_notify_post_published')->default(false)->after('discord_notify_role_changed');
            }
        });
    }

    public function down(): void
    {
        $drops = [];
        foreach ([
            'discord_notify_post_published',
            'discord_notify_role_changed',
            'discord_notify_poll_started',
            'discord_notify_event_starting',
            'discord_notify_member_left',
            'discord_notify_member_joined',
            'discord_notify_application_new',
            'discord_webhook_url',
        ] as $column) {
            if (Schema::hasColumn('guilds', $column)) {
                $drops[] = $column;
            }
        }

        if ($drops !== []) {
            Schema::table('guilds', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
