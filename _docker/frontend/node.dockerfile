# syntax=docker/dockerfile:1
# BuildKit: кэш npm снижает число повторных скачиваний при пересборке
FROM node:22-alpine

WORKDIR /gg

ENV PATH=/gg/node_modules/.bin:$PATH

COPY frontend/package.json frontend/package-lock.json ./

# ECONNRESET / обрывы к registry: больше ретраев и таймауты; при рассинхроне lock — fallback на npm install
RUN --mount=type=cache,target=/root/.npm \
    npm config set fetch-retries 10 \
    && npm config set fetch-retry-mintimeout 15000 \
    && npm config set fetch-retry-maxtimeout 180000 \
    && npm config set fetch-timeout 600000 \
    && (npm ci --no-audit --no-fund || npm install --no-audit --no-fund)

COPY frontend/ ./

# Dev: Vite SSR (server.mjs --dev). Prod: сборка + Node SSR (dist/client + dist/server).
CMD if [ "$NODE_ENV" = "production" ]; then \
      npm run build && npm start; \
    else \
      npm run dev:ssr; \
    fi
