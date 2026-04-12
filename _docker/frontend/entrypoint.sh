#!/bin/sh
set -e
# Пустая папка vite/ на volume проходит [ -d ... ], но без package.json Node ищет index.js и падает.
if [ ! -f /gg/node_modules/express/package.json ] || [ ! -f /gg/node_modules/vite/package.json ]; then
  echo "[entrypoint] node_modules volume is empty/stale — syncing from image cache..."
  if [ ! -f /opt/_node_modules_cache/vite/package.json ]; then
    echo "[entrypoint] FATAL: rebuild image: docker compose build --no-cache gg-frontend"
    exit 1
  fi
  mkdir -p /gg/node_modules
  find /gg/node_modules -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  cp -a /opt/_node_modules_cache/. /gg/node_modules/
  if [ ! -f /gg/node_modules/vite/package.json ]; then
    echo "[entrypoint] FATAL: sync failed (vite missing after cp)"
    exit 1
  fi
  echo "[entrypoint] done."
fi

exec "$@"
