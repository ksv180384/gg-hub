<?php

use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;
use Domains\Access\Models\Role;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $adminGroup = PermissionGroup::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Администрирование']
        );

        Permission::firstOrCreate(
            ['slug' => 'access.admin'],
            [
                'name' => 'Доступ в админку',
                'description' => 'Доступ к разделам управления',
                'permission_group_id' => $adminGroup->id,
            ]
        );

        $gamesGroup = PermissionGroup::firstOrCreate(
            ['slug' => 'games'],
            ['name' => 'Игры']
        );
        Permission::firstOrCreate(
            ['slug' => 'games.manage'],
            [
                'name' => 'Управление играми',
                'description' => 'Создание и редактирование игр',
                'permission_group_id' => $gamesGroup->id,
            ]
        );

        Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Администратор',
                'description' => 'Полный доступ ко всем разделам',
            ]
        );
    }
}
