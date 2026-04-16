<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Подтвердить email всех существующих пользователей без OAuth-провайдера.
     * Они регистрировались до внедрения верификации и не должны быть заблокированы.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('provider')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Откат невозможен без сохранения списка затронутых id
    }
};
