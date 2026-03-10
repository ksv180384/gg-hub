<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Добавляем статус rejected к существующим enum-колонкам.
            $table->enum('status_global', ['pending', 'published', 'draft', 'hidden', 'rejected'])->nullable()->change();
            $table->enum('status_guild', ['pending', 'published', 'draft', 'hidden', 'rejected'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->enum('status_global', ['pending', 'published', 'draft', 'hidden'])->nullable()->change();
            $table->enum('status_guild', ['pending', 'published', 'draft', 'hidden'])->nullable()->change();
        });
    }
};

