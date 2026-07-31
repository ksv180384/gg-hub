<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table): void {
            $table->boolean('is_merging')->default(false)->after('is_active');
            $table->index('is_merging', 'servers_is_merging_idx');
        });

        Schema::create('server_merges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('localization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_server_id')->constrained('servers')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('source_server_ids');
            $table->string('status', 24)->default('pending');
            $table->string('current_stage', 32)->nullable()->default('characters');
            $table->unsignedBigInteger('total_records')->default(0);
            $table->unsignedBigInteger('processed_records')->default(0);
            $table->json('progress')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['localization_id', 'status'], 'srv_merges_loc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_merges');

        Schema::table('servers', function (Blueprint $table): void {
            $table->dropIndex('servers_is_merging_idx');
            $table->dropColumn('is_merging');
        });
    }
};
