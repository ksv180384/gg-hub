# 6. REST API

## Базовые сведения

| Параметр | Значение |
|----------|----------|
| Базовый URL | `{APP_URL}/api/v1` |
| Формат | JSON |
| Авторизация | Cookie-сессия (Sanctum/Fortify session) |
| CSRF | Отключён для `api/*`; фронт шлёт cookie |

Заголовки: `Accept: application/json`, `Content-Type: application/json`, `X-Requested-With: XMLHttpRequest`.

## Публичные эндпоинты (без `auth`)

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/context` | Контекст сайта (игры, пользователь если есть) |
| POST | `/landing/cta-clicks` | Клик CTA лендинга (throttle 60/min) |
| POST | `/email/resend-verification` | Повторная отправка письма |
| GET | `/games` | Список игр |
| GET | `/games/catalog` | Каталог для UI |
| GET | `/games/{game}` | Игра |
| GET | `/games/{game}/game-classes` | Классы персонажей |
| GET | `/games/{game}/journal-posts` | Общий журнал игры |
| GET | `/games/{game}/localizations/{loc}/servers` | Серверы |
| GET | `/guilds` | Список гильдий |
| GET | `/guilds/{guild}` | Карточка гильдии |
| GET | `/guilds/{guild}/application-form` | Поля формы заявки |
| GET | `/posts/{post}` | Публичный пост |
| GET | `/posts/{post}/comments` | Комментарии |
| GET | `/user` | Текущий пользователь (если сессия есть) |

## Авторизованные (`middleware auth`)

### Пользователь

| Метод | Путь |
|-------|------|
| POST | `/user` — обновление профиля |
| GET | `/user/guilds`, `/user/polls`, `/user/applications`, … |
| CRUD | `/characters`, `/games/{game}/characters` |
| CRUD | `/user/posts` |
| GET/PATCH/DELETE | `/notifications` |

### Гильдия — общие

Требуют `guild.member`, если не указано иное.

| Группа | Префикс | Права (примеры) |
|--------|---------|-----------------|
| Календарь | `/guilds/{guild}/events` | `dobavliat-sobytie-kalendar`, … |
| Банк | `/guilds/{guild}/bank/*` | `dobavliat-predmety`, `peredavat-predmety-polzovateliam` |
| DKP | `/guilds/{guild}/dkp/*`, `/members/{char}/dkp` | см. [guild-bank-and-dkp.md](guild-bank-and-dkp.md) |
| Аукцион | `/guilds/{guild}/auction/*` | см. [guild-auction.md](guild-auction.md) |
| История событий | `/guilds/{guild}/event-history` | `dobavliat-sobytie`, … |
| Рейды | `/guilds/{guild}/raids` | `formirovat-reidy`, `udaliat-reidy` |
| Состав | `/guilds/{guild}/roster` | — |
| Заявки | `/guilds/{guild}/applications` | `prosmotr-zaiavok-v-gildiiu`, … |
| Посты гильдии | `/guilds/{guild}/posts` | `publikovat-post` |
| Опросы | `/guilds/{guild}/polls` | `dobavliat-gollosovanie`, … |
| Роли | `/guilds/{guild}/roles` | `dobavliat-rol`, … |
| Настройки | GET `/guilds/{guild}/settings` | — |
| POST/PATCH | `/guilds`, `/guilds/{guild}` | создание/редактирование |

### Виды событий (глобально)

| Метод | Путь |
|-------|------|
| GET/POST | `/event-history-titles` |
| PUT/PATCH/DELETE | `/event-history-titles/{id}` |

### Теги

| Метод | Путь |
|-------|------|
| GET/POST/DELETE | `/tags` |

## Админка API

`middleware: auth, admin.subdomain, permission:admnistrirovanie`

Префикс `/admin/*` внутри `/api/v1`:

| Ресурс | Пути |
|--------|------|
| Посты | `/admin/posts`, publish/reject/block/hide |
| Опросы | `/admin/polls` (+ `prosmatirivat-golosovaniia`) |
| Комментарии | `/admin/comments`, `/admin/application-comments` |
| Пользователи | `/admin/users` |
| Роли/права | `/roles`, `/permissions`, `/permission-groups` |
| Игры | POST `/games`, localizations, servers, game-classes |
| CTA | `/admin/landing-cta-clicks/stats` |

Отдельные permission на операции: `publikovat-post`, `blokirovat-posty`, `dobavliat-igru`, `obshhie-roli`, …

## Web-маршруты (не API)

`routes/web.php`:

- `GET /auth/{provider}/redirect` — Yandex, VK
- `GET /auth/{provider}/callback`
- `GET /email/verify/{id}/{hash}` — верификация
- `GET /sitemap.xml`

## Формат ответов

- Успех: `JsonResource` или `{ data: ... }`
- Ошибки валидации: **422** с `errors` по полям (сообщения на русском)
- Конфликт БД: **409** с `message`
- DKP при выдаче в минус: **422** + `requires_confirmation`, `balance`, `charged`

## Хранилище и ДКП — сводка API

Полная таблица: [guild-bank-and-dkp.md](guild-bank-and-dkp.md#api-префикс-apiv1guildsguild).

Примеры:

```
GET  /api/v1/guilds/1/bank/items
POST /api/v1/guilds/1/bank/grants
GET  /api/v1/guilds/1/dkp/ledger?user_name=...
POST /api/v1/guilds/1/members/5/dkp/adjust
```

## Проверка маршрутов

```bash
docker compose exec gg-php_8.4 php artisan route:list --path=api/v1
```
