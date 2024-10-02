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

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->string('domain_name', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('weapons', function (Blueprint $table) {
            $table->id();
            $table->string('image', 500)->nullable();
            $table->string('name', 255)->nullable();
            $table->unsignedBigInteger('game_id')->index()->nullable();
            $table->timestamps();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->onDelete('cascade');
        });

        Schema::create('skill_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->unsignedBigInteger('game_id')->index()->nullable();
            $table->timestamps();

            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->onDelete('cascade');
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500)->nullable();
            $table->string('name_ru', 500)->nullable();
            $table->unsignedBigInteger('skill_link_id')->nullable()->comment('id ссылки на умения в таблице парсера');
            $table->string('image', 800)->nullable();
            $table->unsignedBigInteger('skill_type_id')->index()->nullable();
            $table->unsignedBigInteger('weapon_id')->index()->nullable();
            $table->unsignedBigInteger('game_id')->index()->nullable();
            $table->timestamps();

            $table->foreign('weapon_id')
                ->references('id')
                ->on('weapons')
                ->onDelete('cascade');
            $table->foreign('skill_type_id')
                ->references('id')
                ->on('skill_types')
                ->onDelete('cascade');
            $table->foreign('game_id')
                ->references('id')
                ->on('games')
                ->onDelete('cascade');
        });

        Schema::create('skill_params', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->json('info_original')->nullable();
            $table->json('info')->nullable();
            $table->unsignedBigInteger('skill_id')->index()->nullable();
            $table->timestamps();

            $table->foreign('skill_id')
                ->references('id')
                ->on('skills')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skill_params', function (Blueprint $table) {
            $table->dropForeign(['skill_id']);
        });
        Schema::dropIfExists('skill_params');

        Schema::table('skill_type', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
        });
        Schema::dropIfExists('skill_type');

        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropForeign(['weapon_id']);
            $table->dropForeign(['skill_type_id']);
        });
        Schema::dropIfExists('skills');

        Schema::table('weapons', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
        });
        Schema::dropIfExists('weapons');
        Schema::dropIfExists('games');

    }
};
