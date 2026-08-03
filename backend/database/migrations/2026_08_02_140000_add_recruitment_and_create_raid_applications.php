<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raids', function (Blueprint $table) {
            $table->boolean('is_recruiting')->default(false)->after('description');
        });

        Schema::create('raid_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raid_id')->constrained('raids')->cascadeOnDelete();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['raid_id', 'character_id']);
            $table->index(['raid_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raid_applications');

        Schema::table('raids', function (Blueprint $table) {
            $table->dropColumn('is_recruiting');
        });
    }
};
