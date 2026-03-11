<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 255)->nullable();
            $table->string('viewer_key', 255)->comment('user:{id} или session:{id} для уникальности');
            $table->timestamps();

            $table->unique(['post_id', 'viewer_key'], 'post_views_post_viewer_unique');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });

        Schema::dropIfExists('post_views');
    }
};
