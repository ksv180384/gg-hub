# Domains (DDD)

Структура backend построена по принципам **Domain-Driven Design**. Каждый домен — отдельный bounded context.

Полная документация: [docs/04-backend.md](../../docs/04-backend.md), [docs/09-baza-dannyh.md](../../docs/09-baza-dannyh.md).

## Расположение

Папка `Domains` находится в **корне Laravel-приложения** (рядом с `app/`), namespace: `Domains\*`.

## Домены (Bounded Contexts)

| Домен | Описание | Основные сущности |
|-------|----------|-------------------|
| **Access** | Права и роли сайта и гильдии | `Role`, `Permission`, `PermissionGroup`, `GuildRole` |
| **Character** | Персонажи пользователя | `Character` |
| **Event** | Календарь и история событий (DKP) | `Event`, `EventHistory`, `EventHistoryTitle`, участники, скриншоты |
| **Game** | Справочники игр | `Game`, `Localization`, `Server` |
| **Guild** | Гильдии, состав, заявки | `Guild`, `GuildMember`, `GuildApplication`, форма заявки |
| **GuildBank** | Хранилище гильдии | `GuildBankItem`, `GuildBankItemTier`, `GuildBankItemGrant` |
| **GuildDkp** | ДКП: балансы и журнал | `GuildUserDkpBalance`, `GuildDkpLedgerEntry` |
| **Poll** | Голосования гильдии | `Poll`, `PollOption`, `PollVote` |
| **Post** | Посты и комментарии | `Post`, `PostComment`, `PostView` |
| **Raid** | Рейды и состав | `Raid`, участники (character) |
| **Tag** | Теги (личные / гильдейские / общие) | `Tag` |
| **User** | Вспомогательная логика пользователя | Actions без собственной модели (`User` в `app/Models`) |

## Правила

- **Один аккаунт (User)** может состоять в разных гильдиях (через разных персонажей).
- **Один персонаж** — только в одной гильдии в контексте игры/локации/сервера.
- **Роли**: у сайта — `Role` + `Permission`; у гильдии — `GuildRole` с привязкой прав (`Permission` scope=guild).
- **Посты**: видимость по scope — гильдия, игра или глобальный.
- **Эвенты календаря**: разовые, ежедневные, еженедельные, ежемесячные.
- **ДКП**: на пользователя в гильдии; синхронизация с историей событий — `SyncEventHistoryDkpLedgerAction`.

## Структура домена (типовая)

```
Domains/
  <DomainName>/
    Actions/     # Сценарии (__invoke)
    Enums/
    Models/
    Rules/       # при необходимости
    Events/
    Exceptions/
```

## Связи между доменами

- `Character` → User, Game, Localization, Server; `GuildMember`.
- `Guild` → Game; заявки, роли, теги; `dkp_enabled`.
- `GuildBank` + `GuildDkp` — выдача предметов списывает/возвращает DKP.
- `Event` → `GuildDkp` при сохранении истории событий.
- `Post` → User; scope guild/game/global.
- `Raid` → Guild; участники — Character.

Миграции — `database/migrations`. HTTP — `app/Http`. Репозитории — `app/Contracts/Repositories`, `app/Repositories/Eloquent`.

## Репозитории

- **Интерфейсы**: `app/Contracts/Repositories/`
- **Реализации**: `app/Repositories/Eloquent/`

Привязка в `AppServiceProvider::register()`.

## Контроллеры

`app/Http/Controllers/Api/` — только маршрутизация: Request → Action → Resource.
