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

        $auctionGroup = PermissionGroup::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'auction',
            ],
            [
                'name' => 'Аукцион / рулетка',
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'upravlenie-ruletkoi',
            ],
            [
                'name' => 'Управление рулеткой',
                'description' => 'Добавление участников на колесо и запуск розыгрыша',
                'permission_group_id' => $auctionGroup->id,
            ]
        );

        $rosterGroup = PermissionGroup::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'roster',
            ],
            [
                'name' => 'Состав',
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'izmeniat-tegi-polzovatelei-gildii',
            ],
            [
                'name' => 'Изменять теги пользователей гильдии',
                'description' => 'Назначение тегов участникам в контексте гильдии (отдельно от личных тегов персонажа)',
                'permission_group_id' => $rosterGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'dobavliat-teg-gildii',
            ],
            [
                'name' => 'Добавлять тег гильдии',
                'description' => 'Создание новых тегов, закреплённых за гильдией (без привязки к пользователю)',
                'permission_group_id' => $rosterGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'udaliat-teg-gildii',
            ],
            [
                'name' => 'Удалять тег гильдии',
                'description' => 'Удаление тегов, закреплённых за гильдией',
                'permission_group_id' => $rosterGroup->id,
            ]
        );

        // Группа прав на редактирование самой гильдии (название/сервер/описание/устав/форма заявки).
        $settingsGroup = PermissionGroup::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'settings',
            ],
            [
                'name' => 'Настройки гильдии',
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'redaktirovanie-dannyx-gildii',
            ],
            [
                'name' => 'Редактирование данных гильдии',
                'description' => 'Изменение названия, локализации, сервера, логотипа и видимости состава',
                'permission_group_id' => $settingsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'izmeniat-tegi-gildii',
            ],
            [
                'name' => 'Изменять теги гильдии',
                'description' => 'Назначение тегов на карточке гильдии (привязка общих и гильдейских тегов)',
                'permission_group_id' => $settingsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'redaktirovanie-opisanie-gildii',
            ],
            [
                'name' => 'Редактирование описания гильдии',
                'description' => 'Изменение текста на вкладке «О гильдии»',
                'permission_group_id' => $settingsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'redaktirovanie-ustav-gildii',
            ],
            [
                'name' => 'Редактирование устава гильдии',
                'description' => 'Изменение текста устава гильдии',
                'permission_group_id' => $settingsGroup->id,
            ]
        );

        Permission::firstOrCreate(
            [
                'scope' => PermissionScope::Guild,
                'slug' => 'redaktirovat-formu-zaiavki-v-giliudiiu',
            ],
            [
                'name' => 'Редактировать форму заявки в гильдию',
                'description' => 'Изменение состава дополнительных полей формы заявки и переключение набора',
                'permission_group_id' => $settingsGroup->id,
            ]
        );
    }
}
