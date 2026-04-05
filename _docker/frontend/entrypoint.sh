#!/bin/sh
# Если volume node_modules пуст (bind-mount перекрыл образовый), копируем из кэша
if [ ! -d /gg/node_modules/express ]; then
  echo "[entrypoint] node_modules volume is empty/stale — syncing from image cache..."
  cp -a /gg/_node_modules_cache/. /gg/node_modules/
  echo "[entrypoint] done."
fi

exec "$@"
