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

        Permission::firstOrCreate(
            ['slug' => 'zablokirovat-polzovatelia'],
            [
                'name' => 'Блокировка пользователей',
                'description' => 'Право блокировать и разблокировать пользователей',
                'permission_group_id' => $adminGroup->id,
            ]
        );

        Permission::firstOrCreate(
            ['slug' => 'izmeniat-rol-polzovatelia'],
            [
                'name' => 'Изменение роли пользователя',
                'description' => 'Право назначать и менять роль пользователя',
                'permission_group_id' => $adminGroup->id,
            ]
        );

        Permission::firstOrCreate(
            ['slug' => 'izmeniat-prava-polzovatelia'],
            [
                'name' => 'Изменение прав пользователя',
                'description' => 'Право назначать и менять права пользователя',
                'permission_group_id' => $adminGroup->id,
            ]
        );

        Permission::firstOrCreate(
            ['slug' => 'obshhie-roli'],
            [
                'name' => 'Общие роли и права',
                'description' => 'Создание и редактирование ролей, прав и категорий прав',
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

        Role::firstOrCreate(
            ['slug' => 'polzovatel'],
            [
                'name' => 'Пользователь',
                'description' => 'Роль по умолчанию для зарегистрированных пользователей',
            ]
        );
    }
}
