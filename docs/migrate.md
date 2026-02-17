# Запуск миграций на проде (Docker)

Хост БД `gg-mariadb` доступен только **внутри Docker-сети**. Команду миграций нужно выполнять в контейнере приложения, а не на хосте.

## Как запустить миграции

Из каталога проекта (где лежит `docker-compose.yml`):

```bash
docker compose exec gg-php_8.2 php artisan migrate
```

Или по имени контейнера:

```bash
docker exec gg-php-8-container php artisan migrate
```

При необходимости добавьте `--force` для production:

```bash
docker compose exec gg-php_8.2 php artisan migrate --force
```

## Переменные окружения

В `.env` на проде для работы **внутри контейнера** должны быть:

- `DB_HOST=gg-mariadb` — имя сервиса из `docker-compose.yml`
- `DB_DATABASE=gg`
- `DB_USERNAME` и `DB_PASSWORD` — как в `MYSQL_USER` / `MYSQL_PASSWORD` в docker-compose

Если `php artisan migrate` запускается **на хосте** (без Docker), тогда нужен `DB_HOST=127.0.0.1` и проброс порта БД с контейнера на хост (в compose уже есть `ports` для MariaDB).
