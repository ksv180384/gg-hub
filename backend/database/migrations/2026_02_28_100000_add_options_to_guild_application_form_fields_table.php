<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guild_application_form_fields', function (Blueprint $table) {
            $table->json('options')->nullable()->after('required');
        });
    }

    public function down(): void
    {
        Schema::table('guild_application_form_fields', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
