<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_party_former_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('constant_party_id');
            $table->unsignedBigInteger('character_id');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at');
            $table->timestamps();

            $table
                ->foreign('constant_party_id', 'cp_former_party_fk')
                ->references('id')
                ->on('constant_parties')
                ->cascadeOnDelete();
            $table
                ->foreign('character_id', 'cp_former_character_fk')
                ->references('id')
                ->on('characters')
                ->cascadeOnDelete();
            $table->unique(
                ['constant_party_id', 'character_id'],
                'cp_former_party_character_unique',
            );
            $table->index(
                ['constant_party_id', 'left_at'],
                'cp_former_party_left_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constant_party_former_members');
    }
};
