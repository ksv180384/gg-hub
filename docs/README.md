# Документация gg-hub

Полная документация монорепозитория **gg** — платформы для поиска, создания и управления игровыми гильдиями в ММОРПГ.

**Продакшен:** [gg-hub.ru](https://gg-hub.ru)  
**Связанный сервис уведомлений:** [notification-gg-hub](../notification-gg-hub) (отдельный репозиторий)

---

## Оглавление

| № | Раздел | Файл | Содержание |
|---|--------|------|------------|
| 1 | Обзор продукта | [01-obzor.md](01-obzor.md) | Назначение, аудитория, функционал, планы |
| 2 | Архитектура | [02-arkhitektura.md](02-arkhitektura.md) | Компоненты, потоки данных, DDD/FSD |
| 3 | Инфраструктура | [03-infrastruktura.md](03-infrastruktura.md) | Docker, окружение, деплой, SSL, почта |
| 4 | Backend | [04-backend.md](04-backend.md) | Laravel, домены, слои, соглашения |
| 5 | Frontend | [05-frontend.md](05-frontend.md) | Vue 3, FSD, маршруты, UI |
| 6 | API | [06-api.md](06-api.md) | REST `/api/v1`, авторизация, права |
| 7 | Realtime | [07-realtime.md](07-realtime.md) | Socket.IO, комнаты, broadcast hooks |
| 8 | Уведомления | [08-uvedomleniya.md](08-uvedomleniya.md) | In-app, Telegram, Discord, notification-hub |
| 9 | База данных | [09-baza-dannyh.md](09-baza-dannyh.md) | Таблицы по доменам, миграции |
| 10 | Тестирование | [10-testirovanie.md](10-testirovanie.md) | Pest, запуск, покрытие |
| 11 | Разработка | [11-razrabotka.md](11-razrabotka.md) | Локальный запуск, чеклисты, соглашения |

### Углублённые темы

| Тема | Файл |
|------|------|
| Хранилище гильдии и ДКП | [guild-bank-and-dkp.md](guild-bank-and-dkp.md) |
| Аукцион гильдии | [guild-auction.md](guild-auction.md) |
| Миграции на проде | [migrate.md](migrate.md) |
| Функционал (краткий обзор) | [features.md](features.md) |
| Домены backend (кратко) | [../backend/Domains/README.md](../backend/Domains/README.md) |

---

## Структура репозитория

```
gg/
├── backend/          # Laravel 12 API (DDD)
├── frontend/         # Vue 3 + Vite + SSR
├── socket_server/    # Fastify + Socket.IO
├── _docker/          # Dockerfile, nginx, php, mysql
├── certbot/          # Let's Encrypt
├── docs/             # Эта документация
└── docker-compose.yml
```

---

## Быстрый старт

```bash
# Из корня репозитория
docker compose up -d --build

# Миграции (внутри контейнера PHP)
docker compose exec gg-php_8.4 php artisan migrate

# Фронтенд (dev) — контейнер gg_frontend, порт 3008
```

Подробнее: [11-razrabotka.md](11-razrabotka.md).

---

## Технологический стек (сводка)

| Слой | Технологии |
|------|------------|
| Backend | PHP 8.4, Laravel 12, Fortify, Socialite, Pest 3 |
| Frontend | Vue 3, TypeScript, Vite 7, Pinia, Vue Router, Tailwind 4, TipTap |
| Realtime | Node.js, Fastify 5, Socket.IO |
| БД | MariaDB |
| Очереди | database driver (`queue:work` в PHP-контейнере) |
| Кэш / сессии | database |
| Почта | docker-mailserver + Roundcube (опционально) |
