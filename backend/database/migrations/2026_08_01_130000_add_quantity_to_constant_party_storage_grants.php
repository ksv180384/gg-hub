<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('constant_party_storage_item_grants', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('granted_by_character_id');
        });
    }

    public function down(): void
    {
        Schema::table('constant_party_storage_item_grants', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
