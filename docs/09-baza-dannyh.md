# 9. База данных

## СУБД

- **MariaDB** (latest image в Docker)
- Кодировка: `utf8mb4_unicode_ci`
- Имя БД по умолчанию: `gg`

Миграции: `backend/database/migrations/` (~100+ файлов).

## Схема по доменам

### Users & Auth

| Таблица | Назначение |
|---------|------------|
| `users` | Аккаунт: email, пароль, аватар, timezone, ban, OAuth, 2FA |
| `sessions` | Сессии (database driver) |

### Access (сайт)

| Таблица | Назначение |
|---------|------------|
| `roles`, `permissions`, `permission_groups` | RBAC сайта |
| `role_permission`, `user_role` | Связи |

### Game

| Таблица | Назначение |
|---------|------------|
| `games` | Игры |
| `localizations` | Локализации игры |
| `servers`, `server_groups` | Серверы, объединение |
| `game_classes` | Классы персонажей (цвет, группа, party_size) |

### Character

| Таблица | Назначение |
|---------|------------|
| `characters` | Персонаж: user, game, localization, server |
| `character_game_class` | Классы на персонаже |
| `character_tag` | Теги персонажа |

### Guild

| Таблица | Назначение |
|---------|------------|
| `guilds` | Гильдия: игра, название, лидер, discord, `dkp_enabled` |
| `guild_members` | Состав: character, guild_role, `dkp_coefficient` |
| `guild_roles`, `guild_role_permission` | Роли в гильдии |
| `guild_applications` | Заявки и приглашения |
| `guild_application_form_fields` | Кастомные поля формы |
| `guild_application_votes`, `guild_application_comments` | Голоса, комментарии |
| `guild_tag`, `character_guild_tag` | Теги гильдии на участниках |

### Tag

| Таблица | Назначение |
|---------|------------|
| `tags` | Справочник тегов (scope: personal/guild/global) |

### Post

| Таблица | Назначение |
|---------|------------|
| `posts` | scope: guild / game / global; статус, preview, block |
| `post_comments` | Комментарии, модерация |
| `post_views` | Просмотры |

### Poll

| Таблица | Назначение |
|---------|------------|
| `guild_polls`, `guild_poll_options`, `guild_poll_votes` | Опросы гильдии |

### Event

| Таблица | Назначение |
|---------|------------|
| `events`, `event_participants`, `event_screenshots` | Календарь |
| `event_histories`, `event_history_participants`, `event_history_screenshots` | История посещений |
| `event_history_titles` | Виды событий + шаблон DKP |

Поля DKP: `dkp_base_points`, `distribute_dkp_to_participants`, `dkp_coefficient`, `dkp_points_override`.

### Raid

| Таблица | Назначение |
|---------|------------|
| `raids` | Дерево рейдов (parent_id) |
| `raid_members` | Состав: character, slot_index |

### GuildBank & GuildDkp

| Таблица | Назначение |
|---------|------------|
| `guild_bank_item_tiers`, `guild_bank_items`, `guild_bank_item_grants` | Хранилище |
| `guild_user_dkp_balances`, `guild_dkp_ledger_entries` | ДКП |

Подробно: [guild-bank-and-dkp.md](guild-bank-and-dkp.md).

### Прочее

| Таблица | Назначение |
|---------|------------|
| `notifications` | In-app уведомления |
| `landing_cta_clicks` | Аналитика CTA лендинга |
| `jobs`, `cache`, `failed_jobs` | Очереди и кэш Laravel |

## Ключевые бизнес-правила (уровень БД/домена)

1. Один `character_id` — один активный `guild_member` в контексте игры/локали/сервера.
2. DKP на паре `(guild_id, user_id)`, не на character.
3. Удаление предмета банка с активными `guild_bank_item_grants` запрещено.
4. Удаление тира с привязанными предметами запрещено.

## Сидеры

| Seeder | Назначение |
|--------|------------|
| `DatabaseSeeder` | Точка входа |
| `GuildPermissionsSeeder` | Права гильдии (банк, рейды, события, …) |
| Другие | Роли сайта, тестовые данные (см. `database/seeders/`) |

```bash
docker compose exec gg-php_8.4 php artisan db:seed
```

## Миграции

```bash
# Создание
php artisan make:migration create_example_table

# Применение (Docker)
docker compose exec gg-php_8.4 php artisan migrate

# Откат последнего batch
php artisan migrate:rollback
```

**Важно (Laravel 12):** при `change()` колонки указывайте все прежние атрибуты, иначе они сбросятся.

Прод: [migrate.md](migrate.md).

## Фабрики и тесты

Фабрики: `database/factories/`.  
Тесты используют `RefreshDatabase` / транзакции (см. [10-testirovanie.md](10-testirovanie.md)).
