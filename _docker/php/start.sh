#!/usr/bin/env bash
# Запуск процессов backend в одном контейнере.
# - queue:work с автоперезапуском в цикле (каждый час процесс завершится и поднимется заново — пригодно для разработки).
# - schedule:work — встроенный foreground-планировщик Laravel 11/12 (равноценен cron-записи `* * * * * php artisan schedule:run`).
# - php-fpm — основной процесс, держит контейнер живым.
# Если любой из вспомогательных процессов завершается — контейнер тоже завершается и Docker перезапускает его.
set -e

cd /var/www/gg

wait_for_db() {
    local host="${DB_HOST:-gg-mariadb}"
    local port="${DB_PORT:-3306}"
    local attempt=1
    local max_attempts=60

    echo "[gg-start] Waiting for database at ${host}:${port}..."

    while ! timeout 2 bash -c ":</dev/tcp/${host}/${port}" 2>/dev/null; do
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "[gg-start] Database is still unavailable after ${max_attempts} attempts."
            return 1
        fi

        echo "[gg-start] Database is not ready yet (${attempt}/${max_attempts}); retrying..."
        attempt=$((attempt + 1))
        sleep 2
    done

    echo "[gg-start] Database is accepting connections."
}

wait_for_db

# Воркер очереди: --tries=3 ограничивает повторные попытки, --backoff=3 задаёт паузу между ретраями,
# --sleep=3 — ожидание при пустой очереди, --max-time=3600 — мягкий рестарт раз в час, чтобы освободить память
# и подхватить обновления кода после deploy.
queue_loop() {
    while true; do
        php artisan queue:work --tries=3 --backoff=3 --sleep=3 --max-time=3600 || true
        sleep 1
    done
}

# Планировщик: запускает scheduled-команды (см. routes/console.php) каждую минуту.
schedule_loop() {
    while true; do
        php artisan schedule:work || true
        sleep 1
    done
}

queue_loop &
QUEUE_PID=$!

schedule_loop &
SCHED_PID=$!

# php-fpm — основной процесс контейнера (foreground). При его падении выходим, тогда Docker рестартанёт всё.
exec php-fpm
