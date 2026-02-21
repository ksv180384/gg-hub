# Стадия сборки: нужны все зависимости (включая dev — Vite, vue-tsc, плагины)
FROM node:22-alpine AS builder

WORKDIR /gg

# Сначала только package.json и lock — чтобы кэш слоя не сбрасывался при изменении кода
COPY ./frontend/package.json ./frontend/package-lock.json* ./

# Устанавливаем ВСЕ зависимости (dev нужны для vite build)
RUN npm ci --include=dev 2>/dev/null || npm install

COPY ./frontend ./

RUN npm run build

# Финальный образ: только статика и serve
FROM node:22-alpine

WORKDIR /gg

RUN npm install -g serve

COPY --from=builder /gg/dist ./dist

CMD ["serve", "-s", "dist", "-l", "3008"]
