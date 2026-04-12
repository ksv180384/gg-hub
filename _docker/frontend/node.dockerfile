# syntax=docker/dockerfile:1
FROM node:22-alpine

WORKDIR /gg

ENV PATH=/gg/node_modules/.bin:$PATH

COPY frontend/package.json frontend/package-lock.json ./

RUN --mount=type=cache,target=/root/.npm \
    npm config set fetch-retries 10 \
    && npm config set fetch-retry-mintimeout 15000 \
    && npm config set fetch-retry-maxtimeout 180000 \
    && npm config set fetch-timeout 600000 \
    && (npm ci --no-audit --no-fund --include=dev || npm install --no-audit --no-fund --include=dev)

# Кэш вне /gg: bind-mount ./frontend:/gg скрывает всё под /gg из образа, иначе entrypoint не видит кэш
RUN cp -a node_modules /opt/_node_modules_cache

COPY frontend/ ./

COPY _docker/frontend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

# В dev: exec node — без `npm run` (при SIGTERM от docker stop npm пишет «command failed», хотя это нормальное завершение).
CMD if [ "$NODE_ENV" = "production" ]; then \
      npm run build && exec node server.mjs; \
    else \
      exec node server.mjs --dev; \
    fi
