# Domains (DDD)

Структура backend построена по принципам **Domain-Driven Design**. Каждый домен — отдельный bounded context.

## Расположение

Папка `Domains` находится в **корне Laravel-приложения** (рядом с `app/`), namespace: `Domains\*`.

## Домены (Bounded Contexts)

| Домен | Описание | Основные сущности |
|-------|----------|-------------------|
| **Game** | Справочники игр (админка) | Game, Localization, Server |
| **Character** | Персонажи пользователя | Character (user + game + localization + server) |
| **Guild** | Гильдии | Guild, GuildMember, GuildApplication |
| **Access** | Права и роли | Role, Permission (сайт), GuildRole (гильдия) |
| **Post** | Посты | Post (scope: guild / game / global) |
| **Raid** | Рейды | Raid, участники (character) |
| **Event** | Эвенты и календарь | Event, EventParticipant, EventScreenshot, recurrence |

## Правила

- **Один аккаунт (User)** может состоять в разных гильдиях (через разных персонажей).
- **Один персонаж** — только в одной гильдии в контексте игры/локации/сервера.
- **Роли**: у сайта — Role + Permission; у гильдии — GuildRole с привязкой прав (Permission).
- **Посты**: видимость по scope_type — гильдия, игра или глобальный.
- **Эвенты**: разовые (once), ежедневные (daily), еженедельные (weekly), ежемесячные (monthly).

## Структура домена (типовая)

```
Domains/
  <DomainName>/
    Actions/     # Сценарии применения (use cases)
    Enums/       # Перечисления домена
    Models/      # Eloquent-модели (сущности)
    Events/      # Доменные события (при необходимости)
    Exceptions/  # Доменные исключения (при необходимости)
```

## Связи между доменами

- `Character` → User, Game, Localization, Server; один `GuildMember` (гильдия).
- `Guild` → Game, Localization, Server; `GuildMember`, `GuildApplication`, `GuildRole` (Access).
- `Post` → User; scope: guild_id/game_id или global (scope_id = null).
- `Raid` → Guild; участники — Character.
- `Event` → Guild; участники — Character; скриншоты — EventScreenshot; recurrence — разовый/ежедневный/еженедельный/ежемесячный.

Миграции — в `database/migrations`. HTTP-слой — в `app/Http`. Запросы к БД вынесены в **репозитории** (см. ниже).

---

## Репозитории (запросы к БД)

Вся работа с БД переиспользуется через репозитории:

- **Интерфейсы**: `app/Contracts/Repositories/` (например, `GameRepositoryInterface`).
- **Реализации**: `app/Repositories/Eloquent/` (например, `EloquentGameRepository`).

Контроллеры и Actions получают репозитории через DI и не обращаются к моделям напрямую для выборок и создания. Привязка интерфейса к реализации — в `AppServiceProvider::register()`.

---

## Где лежат контроллеры

Контроллеры относятся к **слою ввода (HTTP)**, а не к домену, поэтому они находятся в **`app/Http/Controllers`**.

Рекомендуемая структура:

```
app/Http/
  Controllers/
    Controller.php           # Базовый контроллер
    Api/                     # API для фронта
      GameController.php
      LocalizationController.php
      ServerController.php
      CharacterController.php
      GuildController.php
      GuildApplicationController.php
      PostController.php
      RaidController.php
      EventController.php
    Admin/                   # Админка (игры, локализации, сервера, роли)
      GameController.php
      ...
```

Контроллеры только принимают запрос, вызывают **Actions** из доменов и возвращают ответ. Бизнес-логика остаётся в `Domains/*/Actions` и моделях.
