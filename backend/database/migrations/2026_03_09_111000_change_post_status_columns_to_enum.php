<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Статусы видимости поста:
            // - status_global — в разделе «Общие»
            // - status_guild  — в разделе гильдии
            // Возможные значения:
            // pending   — на модерации / в обработке
            // published — опубликован
            // draft     — черновик
            // hidden    — скрыт
            $table->enum('status_global', ['pending', 'published', 'draft', 'hidden'])->nullable()->change();
            $table->enum('status_guild', ['pending', 'published', 'draft', 'hidden'])->nullable()->change();
            $table->enum('type', ['global', 'guild'])->nullable()->default(null)->change()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('status_global')->nullable()->change();
            $table->string('status_guild')->nullable()->change();
            $table->string('type')->nullable()->change();
        });
    }
};

