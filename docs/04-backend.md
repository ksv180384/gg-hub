# 4. Backend (Laravel)

## Версии и пакеты

| Пакет | Версия / назначение |
|-------|---------------------|
| PHP | 8.4 |
| Laravel | 12 |
| Fortify | Авторизация (регистрация, логин, 2FA, сброс пароля) |
| Socialite | OAuth (Yandex, VK) |
| Pest | 3 — тесты |

Конфигурация middleware: `bootstrap/app.php` (не `app/Http/Kernel.php`).  
API-префикс: `/api/v1`.

## Структура каталогов

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     # REST-контроллеры
│   │   ├── Controllers/Admin/   # (если есть отдельно)
│   │   ├── Controllers/Auth/
│   │   ├── Middleware/
│   │   ├── Requests/            # FormRequest по сущностям
│   │   └── Resources/           # JsonResource
│   ├── Models/                  # User, GameClass, Notification, …
│   ├── Repositories/Eloquent/
│   └── Contracts/Repositories/
├── Domains/                     # DDD bounded contexts
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── console.php
└── tests/
    ├── Feature/
    └── Unit/
```

## Домены (Bounded Contexts)

| Домен | Путь | Описание |
|-------|------|----------|
| **Access** | `Domains/Access/` | Роли и права сайта и гильдии (`Role`, `Permission`, `GuildRole`) |
| **Character** | `Domains/Character/` | Персонажи пользователя |
| **Event** | `Domains/Event/` | Календарь, история событий, виды событий, DKP-поля участников |
| **Game** | `Domains/Game/` | Игры, локализации, серверы |
| **Guild** | `Domains/Guild/` | Гильдии, состав, заявки, приглашения, теги |
| **GuildBank** | `Domains/GuildBank/` | Хранилище: тиры, предметы, выдачи |
| **GuildDkp** | `Domains/GuildDkp/` | Балансы и журнал ДКП |
| **Poll** | `Domains/Poll/` | Голосования гильдии |
| **Post** | `Domains/Post/` | Посты и комментарии, модерация |
| **Raid** | `Domains/Raid/` | Дерево рейдов и состав |
| **Tag** | `Domains/Tag/` | Теги (личные, гильдейские, общие) |
| **User** | `Domains/User/` | Вспомогательная логика пользователя |

Модели вне доменов: `app/Models/User.php`, `GameClass`, `Notification`, `LandingCtaClick`.

## Типовой Action

```php
final class CreateGuildAction
{
    public function __invoke(CreateGuildData $data): Guild
    {
        // транзакция, доменная логика, события
    }
}
```

Контроллер:

```php
public function store(StoreGuildRequest $request, CreateGuildAction $action): GuildResource
{
    $guild = $action($request->validated());

    return new GuildResource($guild);
}
```

## Middleware (алиасы)

| Alias | Класс | Назначение |
|-------|-------|------------|
| `admin.subdomain` | `EnsureAdminSubdomain` | Запрос с admin-субдомена |
| `ensure.not.banned` | `EnsureUserNotBanned` | Заблокированные не пишут контент |
| `permission` | `EnsureUserHasPermission` | Права сайта (slug) |
| `guild.member` | `EnsureUserIsGuildMember` | Участник гильдии |
| `guild.role.permission` | `EnsureUserHasGuildRolePermission` | Права роли в гильдии |

Несколько slug в `guild.role.permission:a,b` — достаточно **одного** из перечисленных.

## Права гильдии (примеры slug)

Сидер: `database/seeders/GuildPermissionsSeeder.php`.

| Slug | Операции |
|------|----------|
| `dobavliat-predmety` | CRUD предметов банка, тиры |
| `udaliat-predmety` | Удаление предметов |
| `peredavat-predmety-polzovateliam` | Выдача/отзыв, ручной ДКП, коэффициент |
| `formirovat-reidy` | Создание/редактирование рейдов |
| `udaliat-reidy` | Удаление рейдов |
| `dobavliat-sobytie-kalendar` | События календаря |
| `dobavliat-sobytie` | История событий |
| `prosmotr-zaiavok-v-gildiiu` | Заявки |
| `podtverzdenie-ili-otklonenie-zaiavok` | Принятие/отклонение, приглашения |
| `publikovat-post` | Модерация постов гильдии |
| `redaktirovanie-dannyx-gildii` | Настройки (в т.ч. `dkp_enabled`) |

Полный список — в сидере и в `frontend/src/shared/api/guildPermissionSlugs.ts`.

## Обработка ошибок БД

Для `api/*` `QueryException` преобразуется в JSON 409 с понятным текстом (дубликат, FK).

## Artisan — полезные команды

```bash
php artisan route:list --path=api
php artisan migrate
php artisan db:seed
php artisan test --compact
vendor/bin/pint --dirty
```

## Fortify и web-маршруты

- `routes/web.php` — OAuth redirect/callback, верификация email, sitemap
- CSRF отключён для `api/*` (cookie API)

## Связанные документы

- [06-api.md](06-api.md) — эндпоинты
- [guild-bank-and-dkp.md](guild-bank-and-dkp.md) — банк и ДКП
- [09-baza-dannyh.md](09-baza-dannyh.md) — схема БД
- [10-testirovanie.md](10-testirovanie.md) — Pest
