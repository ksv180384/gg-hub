# 3. Инфраструктура и развёртывание

## Docker Compose — сервисы

Файл: `docker-compose.yml` в корне репозитория.

| Сервис | Контейнер | Порты / назначение |
|--------|-----------|-------------------|
| `gg-nginx` | `gg-nginx-container` | 80/443 — reverse proxy, PHP, статика `frontend/dist`, SSL |
| `gg-frontend` | `gg_frontend` | 3008 (Vite dev), 24678 (HMR) |
| `gg-php_8.4` | `gg-php-8-container` | PHP-FPM + queue + schedule |
| `gg-mariadb` | `gg-mariadb-container` | MariaDB, БД `gg` |
| `phpmyadmin` | — | 3002 |
| `socket-server-nodejs` | `socket_server` | 3007 |
| `certbot` | `certbot` | Автообновление Let's Encrypt |
| `mailserver` | `gg-mailserver` | SMTP/IMAP (Postfix/Dovecot) |
| `roundcube` | `gg-roundcube` | Webmail |

Сеть Docker: `gg`.  
Закомментирован: `gg-redis` (кэш/очереди на database).

### Volumes

- `frontend_node_modules` — node_modules фронта в Linux-контейнере
- `socket_server_node_modules` — то же для socket_server
- `mailserver_*`, `roundcube_data` — почта

## Nginx

Конфиг: `_docker/nginx/${NGINX_CONFIG}` (монтируется в `/etc/nginx/conf.d/default.conf`).

Типичные задачи:

- Проксирование PHP → `gg-php_8.4`
- Раздача `frontend/dist` (production build)
- Прокси `/socket.io` → socket_server (в dev — через Vite)
- ACME challenge для certbot (`/var/www/certbot`)

## Переменные окружения

### Корень (Docker)

Файл `.env` (пример без `.env.example` в корне — ориентируйтесь на `docker-compose.yml`):

| Переменная | Назначение |
|------------|------------|
| `NGINX_TO_PORT`, `NGINX_FROM_PORT` | HTTP |
| `NGINX_TO_PORT_SSL`, `NGINX_FROM_PORT_SSL` | HTTPS |
| `NGINX_CONFIG` | Имя файла конфига nginx |
| `MYSQL_USER`, `MYSQL_PASSWORD` | MariaDB |
| `DB_TO_PORT`, `DB_FROM_PORT` | Проброс порта БД на хост |
| `CHOKIDAR_USEPOLLING` | HMR в Docker на WSL |
| `VITE_SSR_API_ORIGIN` | Origin API для SSR (например `http://gg-nginx:81`) |
| `VITE_SOCKET_DEV_PROXY_TARGET` | Прокси socket в dev |

### Backend

Файл: `backend/.env.example` → копировать в `backend/.env`.

Ключевые группы:

| Группа | Переменные |
|--------|------------|
| Приложение | `APP_URL`, `APP_KEY`, `APP_DEBUG`, `APP_DOMAIN` |
| БД | `DB_HOST=gg-mariadb` (в Docker), `DB_DATABASE=gg` |
| Сессии | `SESSION_DRIVER`, `SESSION_DOMAIN` (например `.gg-hub.local`) |
| Очереди | `QUEUE_CONNECTION=database` |
| Уведомления | `NOTIFICATIONS_LOG_CHANNEL`, `NOTIFICATION_HUB_*` |
| OAuth | `YANDEX_*`, `VKONTAKTE_*` |
| Почта | `MAIL_*` |

### Frontend

| Файл | Назначение |
|------|------------|
| `frontend/.env.development` | `VITE_APP_HOST`, HMR, `VITE_SOCKET_URL` |
| `frontend/.env.production` | `VITE_SITE_URL`, SEO |

## Миграции на продакшене

БД доступна по имени хоста `gg-mariadb` **только внутри Docker-сети**.

```bash
docker compose exec gg-php_8.4 php artisan migrate --force
```

Подробнее: [migrate.md](migrate.md).

## SSL (Let's Encrypt)

- Сервис `certbot` каждые 12 часов вызывает `certbot renew`
- После обновления — `nginx -s reload` в контейнере nginx
- Сертификаты: `./certbot/conf`, webroot: `./certbot/www`
- Wildcard через Cloudflare: `certbot/cloudflare.ini.example`

## Почта

- **mailserver** — docker-mailserver, hostname `mail778.gg-hub.ru`
- **roundcube** — веб-клиент, SQLite
- Настройка: `_docker/mailserver/SETUP.md`

Production: `MAIL_MAILER=smtp`, `MAIL_HOST=mailserver`.  
Development: `MAIL_MAILER=log`.

## Деплой

В репозитории **нет** CI/CD (`.github/workflows`, GitLab CI и т.п.). Деплой выполняется вручную:

1. `git pull` на сервере
2. `docker compose up -d --build`
3. `php artisan migrate --force` в контейнере PHP
4. Сборка фронта: `npm run build` (в контейнере или на хосте → `frontend/dist`)
5. При необходимости — `php artisan config:cache`, `route:cache`

Сайт продакшена: **gg-hub.ru**.

## phpMyAdmin

Порт **3002**, подключение к `gg-mariadb`.  
`PMA_ARBITRARY=1`, лимит загрузки 500M.

## Healthcheck

Laravel: `GET /up` (стандарт Laravel 11+).
