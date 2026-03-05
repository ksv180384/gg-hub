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
    }
}
