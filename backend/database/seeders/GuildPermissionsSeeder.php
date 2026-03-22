<?php

use Domains\Access\Enums\PermissionScope;
use Domains\Access\Models\Permission;
use Domains\Access\Models\PermissionGroup;
use Illuminate\Database\Seeder;

class GuildPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $raidsGroup = PermissionGroup::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'raids',
            ],
            [
                'name' => 'Рейды',
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'formirovat-reidy',
            ],
            [
                'name' => 'Формировать рейды',
                'description' => 'Добавление и редактирование рейдов и подрейдов',
                'permission_group_id' => $raidsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'udaliat-reidy',
            ],
            [
                'name' => 'Удалять рейды',
                'description' => 'Удаление рейдов и подрейдов',
                'permission_group_id' => $raidsGroup->id,
            ]
        );

        $pollsGroup = PermissionGroup::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'polls',
            ],
            [
                'name' => 'Голосования',
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'dobavliat-gollosovanie',
            ],
            [
                'name' => 'Добавлять голосование',
                'description' => 'Создание новых голосований',
                'permission_group_id' => $pollsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'redaktirovat-gollosovanie',
            ],
            [
                'name' => 'Редактировать голосование',
                'description' => 'Изменение существующих голосований',
                'permission_group_id' => $pollsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'zakryvat-gollosovanie',
            ],
            [
                'name' => 'Закрывать голосование',
                'description' => 'Закрытие голосований (запрет новых голосов)',
                'permission_group_id' => $pollsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'sbrasyvat-gollosovanie',
            ],
            [
                'name' => 'Сбрасывать голосование',
                'description' => 'Сброс всех голосов (участники смогут голосовать снова)',
                'permission_group_id' => $pollsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'udaliat-gollosovanie',
            ],
            [
                'name' => 'Удалять голосование',
                'description' => 'Удаление голосований',
                'permission_group_id' => $pollsGroup->id,
            ]
        );
    }
}
