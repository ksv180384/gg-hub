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
    && (npm ci --no-audit --no-fund || npm install --no-audit --no-fund)

# Сохраняем node_modules из образа — при старте скопируем в volume, если он пуст
RUN cp -a node_modules /gg/_node_modules_cache

COPY frontend/ ./

COPY _docker/frontend/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD if [ "$NODE_ENV" = "production" ]; then \
      npm run build && npm start; \
    else \
      npm run dev:ssr; \
    fi
