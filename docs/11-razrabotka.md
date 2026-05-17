# 11. Руководство разработчика

## Требования

- Docker + Docker Compose
- Git
- (Опционально) Node.js 20+ локально для фронта без Docker

## Первый запуск

```bash
git clone <repo> gg
cd gg

# Скопировать env
cp backend/.env.example backend/.env
# Настроить корневой .env для compose (порты, пароли MySQL)

docker compose up -d --build

# Ключ приложения
docker compose exec gg-php_8.4 php artisan key:generate

# Миграции
docker compose exec gg-php_8.4 php artisan migrate

# (Опционально) сидеры
docker compose exec gg-php_8.4 php artisan db:seed
```

### Локальные домены

Для cookie-сессии на субдоменах добавьте в `/etc/hosts`:

```
127.0.0.1 gg-hub.local admin.gg-hub.local
```

`APP_URL=http://gg-hub.local`, `SESSION_DOMAIN=.gg-hub.local`.

### URL сервисов (типичные порты)

| Сервис | URL |
|--------|-----|
| Сайт (nginx) | http://gg-hub.local (порт из `.env`) |
| Vite dev | http://localhost:3008 |
| phpMyAdmin | http://localhost:3002 |
| Socket server | http://localhost:3007 |

## Ежедневная работа

### Backend

```bash
docker compose exec gg-php_8.4 php artisan make:model ...
docker compose exec gg-php_8.4 php artisan test --compact --filter=...
docker compose exec gg-php_8.4 vendor/bin/pint --dirty
```

Новая доменная логика → `Domains/<Domain>/Actions/`, не в контроллер.

### Frontend

```bash
docker compose exec gg_frontend npm run dev
# или локально в frontend/
npm run dev
npm run build
```

После изменений UI при production-сборке — пересобрать `dist/`.

### Socket

Логи: `docker compose logs -f socket-server-nodejs`.

## Чеклист новой фичи (API + UI)

### Backend

1. Миграция(и) в `database/migrations/`
2. Модель в `Domains/.../Models/` (или `app/Models`)
3. `FormRequest` с `rules()`, `messages()`, `attributes()`
4. `Action` с `__invoke`
5. `JsonResource` для ответа
6. Маршрут в `routes/api.php` + middleware прав
7. Feature-тест Pest
8. `pint` + `php artisan test`

### Frontend

1. API-модуль в `shared/api/`
2. Feature/widget/page по FSD
3. Маршрут в `router/index.ts` + `meta.requiresAuth` / `permission`
4. Обязательные поля со `*`, mobile layout
5. При необходимости — подписка на Socket.IO

## Соглашения (кратко)

| Слой | Правило |
|------|---------|
| Backend | DDD, Actions `__invoke`, Resources, без логики в контроллерах |
| Frontend | FSD, импорт вниз, `LightboxImage`, теги через `rosterTagDisplay` |
| API | Префикс `/api/v1`, cookie auth |
| Коммиты | Только по запросу пользователя |

## Отладка

| Проблема | Решение |
|----------|---------|
| 401 на API | Проверить cookie, `SESSION_DOMAIN`, same-site |
| Vite manifest error | `npm run build` или `npm run dev` |
| Socket не подключается | Прокси `/socket.io`, порт 3007, логи socket_server |
| Миграции не находят БД | Запускать внутри `gg-php_8.4`, `DB_HOST=gg-mariadb` |
| WSL + HMR | `CHOKIDAR_USEPOLLING=true` |

## Документация

Полное оглавление: [README.md](README.md).

Углублённые темы:

- [guild-bank-and-dkp.md](guild-bank-and-dkp.md)
- [migrate.md](migrate.md)

## Полезные файлы правил IDE

- `.cursor/rules/backend-ddd.mdc`
- `.cursor/rules/frontend-fsd.mdc`
- `backend/AGENTS.md` — Laravel Boost guidelines
