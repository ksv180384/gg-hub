<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_history_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('event_histories', function (Blueprint $table) {
            $table->foreignId('event_history_title_id')
                ->nullable()
                ->after('guild_id')
                ->constrained('event_history_titles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_history_title_id');
        });

        Schema::dropIfExists('event_history_titles');
    }
};

