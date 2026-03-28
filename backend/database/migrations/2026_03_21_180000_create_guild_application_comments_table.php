<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('guild_application_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guild_application_id')->constrained('guild_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('guild_application_comments')->nullOnDelete();
            $table->foreignId('replied_to_comment_id')->nullable()->constrained('guild_application_comments')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guild_application_comments');
    }
};
