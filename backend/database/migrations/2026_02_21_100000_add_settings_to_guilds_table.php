<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guilds', function (Blueprint $table) {
            if (!Schema::hasColumn('guilds', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('description');
            }
            if (!Schema::hasColumn('guilds', 'show_roster_to_all')) {
                $table->boolean('show_roster_to_all')->default(false)->after('logo_path');
            }
            if (!Schema::hasColumn('guilds', 'about_text')) {
                $table->text('about_text')->nullable()->after('show_roster_to_all');
            }
            if (!Schema::hasColumn('guilds', 'charter_text')) {
                $table->text('charter_text')->nullable()->after('about_text');
            }
        });
    }

    public function down(): void
    {
        $drops = [];
        if (Schema::hasColumn('guilds', 'charter_text')) {
            $drops[] = 'charter_text';
        }
        if (Schema::hasColumn('guilds', 'about_text')) {
            $drops[] = 'about_text';
        }
        if (Schema::hasColumn('guilds', 'show_roster_to_all')) {
            $drops[] = 'show_roster_to_all';
        }
        if (Schema::hasColumn('guilds', 'logo_path')) {
            $drops[] = 'logo_path';
        }
        if ($drops !== []) {
            Schema::table('guilds', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
