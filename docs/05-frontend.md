# 5. Frontend (Vue 3)

## Стек

| Технология | Назначение |
|------------|------------|
| Vue 3 | UI framework |
| TypeScript | Типизация |
| Vite 7 | Сборка, dev-server, HMR |
| Vue Router | Маршрутизация |
| Pinia | Глобальное состояние |
| Tailwind CSS 4 | Стили |
| radix-vue | Доступные примитивы |
| TipTap | Rich-text редактор постов |
| Axios | HTTP (через `shared/api/http.ts`) |

SSR: `entry-server.ts`, `entry-client.ts`, `ssr/hydrationFlag.ts`.

## FSD — слои и правила

```
app → pages → widgets → features → entities → shared
```

**Запрещено:** импортировать из верхних слоёв (например, `shared` не импортирует `features`).

### `app/`

- `layouts/MainLayout.vue` — шапка, сайдбар игры, контент, промо-журнала

### `pages/` — экраны

| Путь | Назначение |
|------|------------|
| `auth/` | login, register, forgot/reset password |
| `home/` | Лендинг, журнал игры |
| `guilds/` | Список, карточка, roster, bank, DKP, raids, events, polls, auction, applications, settings, calendar |
| `posts/`, `my-posts/` | Просмотр и управление постами |
| `characters/` | Персонажи пользователя |
| `applications/` | Мои заявки |
| `profile/` | Профиль |
| `admin/` | Админка (journal, users, roles, games, tags, polls, comments) |
| `not-found/` | 404 |

### `widgets/`

Крупные блоки: `header`, `game-sidebar`, `guild-bank`, `guild-dkp-ledger`, `guild-calendar`, `guild-auction-roulette`, `raid-composition-modal`, `spin-wheel`, …

### `features/`

Сценарии + composables: `guild-bank`, `guild-dkp`, `guild-settings`, `guild-calendar`, `guild-auction-roulette`, …

### `entities/`

- `game/GameCatalogCard`
- `character/CharacterCard`, `CharacterClassBadge`

### `shared/`

| Подпапка | Содержание |
|----------|------------|
| `api/` | HTTP-клиент, API-модули по доменам |
| `ui/` | Button, Input, Dialog, Calendar, RichTextEditor, LightboxImage, … |
| `lib/` | DKP-расчёт, теги, SEO, xlsx, toast, sockets |

Публичный API UI — через `index.ts` в каждом компоненте.

## Pinia stores

| Store | Назначение |
|-------|------------|
| `auth` | Пользователь, права, `hasPermission` |
| `siteContext` | Текущая игра, каталог, контекст сайта |
| `theme` | Тема оформления |
| `adminJournal` | Состояние админ-журнала |
| `routeLoading` | Индикатор загрузки маршрута |

## Маршрутизация

Файл: `frontend/src/router/index.ts`.

### Meta-поля

| Поле | Назначение |
|------|------------|
| `requiresAuth` | Редирект на login при 401 |
| `permission` | Slug права сайта (админка) |
| `contentShell` | Узкая колонка контента |
| `journalBanner` | Промо-баннер журнала справа |

### Короткие ссылки

| URL | Редирект |
|-----|----------|
| `/a:id` | `/guilds/:id/application-form` |
| `/g:id` | `/guilds/:id/info` |

### Зоны доступа

**Гостевые:** `/login`, `/register`, `/forgot-password`, `/reset-password`, публичные `/`, `/posts/:id`, `/guilds/:id/info`, `/guilds/:id/application-form`.

**Авторизованные (`requiresAuth`):** профиль, персонажи, гильдии (roster, bank, events, …), заявки.

**Админка (`/admin/*`):** только на субдомене `admin.*` с permission `admnistrirovanie`. Без прав — редирект на основной домен.

| Маршрут | name |
|---------|------|
| `/admin/journal` | admin-journal |
| `/admin/users` | admin-users |
| `/admin/games` | admin-games |
| `/admin/roles` | admin-roles |
| `/admin/permissions` | admin-permissions |
| `/admin/polls` | admin-polls (`prosmatirivat-golosovaniia`) |
| `/admin/comments` | admin-comments |
| `/admin/tags` | admin-tags |

## API-клиент

Базовый модуль: `shared/api/http.ts` + interceptors (`http-interceptors.ts`, SSR-вариант).

Модули по доменам: `guildsApi.ts`, `guildBankApi.ts`, `guildDkpApi.ts`, `postsApi.ts`, `authApi.ts`, …

Запросы с `credentials: 'include'` для cookie-сессии.

## UI-соглашения

- **Обязательные поля** — звёздочка в лейбле (`*` или `aria-hidden`)
- **Адаптивность** — mobile-first, Tailwind `sm:`, `md:`, `min-w-0` для переполнения
- **Теги состава** — `shared/lib/rosterTagDisplay` (цвет, сортировка, «+N»)
- **Увеличение изображений** — только `LightboxImage` из `shared/ui`

## Сборка

```bash
cd frontend
npm install
npm run dev      # разработка, :3008
npm run build    # production → dist/
```

Если UI не обновляется — пересобрать или перезапустить dev. SSR использует `VITE_SSR_API_ORIGIN` для запросов с сервера.

## Realtime на клиенте

Подключение Socket.IO — `shared/lib/` (см. [07-realtime.md](07-realtime.md)).  
В dev прокси `/socket.io` → `socket_server:3007`.

## Связанные документы

- [06-api.md](06-api.md)
- [07-realtime.md](07-realtime.md)
- [02-arkhitektura.md](02-arkhitektura.md)
