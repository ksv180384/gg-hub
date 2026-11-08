# Обновление PHP, Node.js и nginx — 28 октября 2026

## Что изменено

| Где | Было в репозитории | Стало |
| --- | --- | --- |
| Backend PHP-FPM, очередь и планировщик | `php:8.4-fpm` | `php:8.4.27-fpm-trixie` |
| Node.js внутри PHP-контейнера | Debian `nodejs`/`npm`, затем NodeSource `setup_current.x`, `npm@latest` | Node.js `22.23.4` и его npm из официального `node:22.23.4-trixie-slim` |
| Frontend SSR и Socket.IO | `node:22-alpine` | `node:22.23.4-alpine` |
| Локальная разработка frontend | `.nvmrc`: `22` | `22.23.4` |
| Reverse proxy | `nginx:stable-alpine` | `nginx:1.30.6-alpine` |
| Roundcube | `roundcube/roundcubemail:latest`, собственный PHP | Обязательно скачать свежий образ и проверить встроенный PHP перед пересозданием |

Исходные теги плавающие: по ним нельзя определить патч-версии уже запущенных контейнеров. Продакшен в рамках этой задачи не подключался и не обновлялся.

Источники:

- [PHP, 21 и 23 октября](https://www.php.net/archive/2026.php): PHP 8.4.27 исправляет критические RCE CVE-2026-109618 и CVE-2026-109619. Дополнительно отключён `session.upload_progress.enabled` в backend и Roundcube по рекомендации для CVE-2026-115560; это не мешает обычной загрузке файлов и не заменяет обновление PHP.
- [Node.js, 21 октября](https://nodejs.org/en/blog/vulnerability/october-2026-security-releases): 22.23.4, 24.22.1 и 26.12.1 исправляют уязвимости, включая Brotli RCE CVE-2026-111497. Максимальная официальная оценка этого набора — high. Сохраняем используемую проектом ветку 22.
- [nginx advisories](https://nginx.org/en/security_advisories.html): stable 1.30.6 исправляет перечисленные уязвимости, включая CVE-2026-15176, CVE-2026-93479, CVE-2026-95101, CVE-2026-97629 и CVE-2026-97205. Для последних пяти официальная оценка — medium.

Это исправления известных на дату проверки проблем; фиксированные теги нужно обновлять при следующих security-релизах.

## Обновление на проде

Выполнять из каталога проекта, с теми же Compose-файлами, `.env` и именем Compose-проекта, которые используются сейчас. В корневом `.env` для frontend должен быть `NODE_ENV=production`. Учитывайте короткий перерыв в обслуживании и переподключение WebSocket; frontend при старте собирает `dist` и требует времени и памяти.

1. Сохраните резервную копию БД, `backend/storage`, `roundcube_data`, конфигурации и текущей сборки `frontend/dist` штатным способом. Перед обновлением сохраните версии и идентификаторы образов:

```bash
docker compose images
docker compose exec -T gg-php_8.4 php -v
docker compose exec -T gg-php_8.4 node -v
docker compose exec -T gg-frontend node -v
docker compose exec -T socket-server-nodejs node -v
docker compose exec -T gg-nginx nginx -v
docker compose exec -T roundcube php -v
```

2. Доставьте изменения репозитория на сервер (после коммита/публикации — `git pull --ff-only`). Проверьте конфигурацию и подготовьте образы, пока старые контейнеры продолжают работать:

```bash
docker compose config --quiet
docker compose pull gg-nginx roundcube
docker compose build --pull --no-cache gg-php_8.4 gg-frontend socket-server-nodejs
```

При ошибке остановитесь. `--pull` получает базовые образы, `--no-cache` также обновляет устанавливаемые системные пакеты. БД и mailserver в список обновления не входят.

3. До пересоздания проверьте полученные образы. Эти команды запускают временные контейнеры без зависимостей и обходят обычные entrypoint, очередь и сборку frontend:

```bash
docker compose run --rm --no-deps -T --entrypoint php gg-php_8.4 -r 'echo PHP_VERSION, PHP_EOL; exit(PHP_VERSION === "8.4.27" && !ini_get("session.upload_progress.enabled") ? 0 : 1);'

for service in gg-php_8.4 gg-frontend socket-server-nodejs; do
    docker compose run --rm --no-deps -T --entrypoint node "$service" -e 'console.log(process.versions.node); process.exit(process.versions.node === "22.23.4" ? 0 : 1)' || exit 1
done

docker compose run --rm --no-deps -T --entrypoint nginx gg-nginx -v

docker compose run --rm --no-deps -T --entrypoint php roundcube -r '$floors = ["8.2" => "8.2.35", "8.3" => "8.3.36", "8.4" => "8.4.27", "8.5" => "8.5.12"]; $branch = PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION; $ok = isset($floors[$branch]) && version_compare(PHP_VERSION, $floors[$branch], ">=") && !ini_get("session.upload_progress.enabled"); echo PHP_VERSION, PHP_EOL; exit($ok ? 0 : 1);'
```

Каждая команда должна завершиться с кодом 0; nginx должен вывести `nginx/1.30.6`. Если Roundcube всё ещё содержит старый PHP, обновление не продолжать: нужен пересобранный upstream-образ с исправлением. PHP backend не обновляет PHP Roundcube. Проверка Roundcube разрешает только поддерживаемые исправленные ветки 8.2–8.5.

Если на хосте есть Node.js, все проверки можно выполнить одной командой с автоматической проверкой nginx и отказом при первой ошибке:

```bash
node scripts/check-runtime-security.mjs
```

4. Пересоздайте только нужные сервисы. nginx пересоздаётся последним, чтобы заново разрешить адрес PHP-FPM после замены контейнера:

```bash
docker compose up -d --no-deps --force-recreate gg-php_8.4 socket-server-nodejs roundcube gg-frontend
docker compose up -d --no-deps --force-recreate gg-nginx
```

Обычный `docker compose restart` оставляет старые образы. Не выполняйте `down -v`: тома должны сохраниться. Эти изменения не требуют миграций БД. При пересоздании PHP также заменяются процессы очереди и планировщика; учитывайте выполняющиеся длительные задания.

5. Проверьте запущенные контейнеры:

```bash
docker compose ps
docker compose exec -T gg-php_8.4 php -v
docker compose exec -T gg-php_8.4 node -v
docker compose exec -T gg-php_8.4 php -r 'var_export(ini_get("session.upload_progress.enabled")); echo PHP_EOL;'
docker compose exec -T gg-frontend node -v
docker compose exec -T socket-server-nodejs node -v
docker compose exec -T gg-nginx nginx -v
docker compose exec -T gg-nginx nginx -t
docker compose exec -T roundcube php -v
docker compose exec -T roundcube php -r 'var_export(ini_get("session.upload_progress.enabled")); echo PHP_EOL;'
docker compose logs --tail=100 gg-php_8.4 gg-frontend socket-server-nodejs gg-nginx roundcube
curl --fail --silent --show-error --output /dev/null https://gg-hub.ru/
curl --fail --silent --show-error --output /dev/null https://mail778.gg-hub.ru/
```

Ожидаются PHP 8.4.27 в backend, Node.js 22.23.4 во всех трёх сервисах, nginx 1.30.6, исправленный PHP в Roundcube и выключенный upload progress (пустая строка либо `0`). При наличии Node.js на хосте выполните также `node scripts/check-runtime-security.mjs --running` — он проверяет именно работающие контейнеры. Проверьте авторизацию, загрузку изображения, SSR-страницу, чат, обработку очереди и вход в webmail.

## Откат и дополнительные PHP-контейнеры

Не удаляйте прежние образы и резервные копии до успешной проверки. При проблеме восстановите ранее сохранённые image ID через отдельный Compose override с `image` для затронутых сервисов и `docker compose ... up -d --no-deps --no-build --force-recreate`; восстановите соответствующую сборку `frontend/dist`. Если обновление Roundcube изменило его SQLite-схему, восстановите резервную копию `roundcube_data` вместе со старым образом. Возврат к уязвимой версии допустим лишь как временная аварийная мера с ограничением доступа к сервису.

`phpmyadmin` находится в профиле `dev`. Если он всё же запущен на проде, у него также отдельный PHP: проверьте `docker compose --profile dev exec -T phpmyadmin php -v`, скачайте исправленный образ и пересоздайте этот сервис отдельно. Версия PHP хостовой ОС не влияет на интерпретатор внутри контейнеров.

## Автоматические проверки

```bash
node --test scripts/runtime-security.test.mjs
docker compose config --quiet
```

Тесты проверяют границы исправленных версий, отказ для неподдерживаемых и предварительных релизов, обработку ошибок Docker, режимы проверки образов/контейнеров, фиксацию Docker-версий и настройки PHP. Они не заменяют сборку и проверку приложения на обновлённых образах.
