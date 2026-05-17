# 7. Realtime (Socket.IO)

## Назначение

Сервис `socket_server/` доставляет события в браузер без polling:

- синхронизация рейдов и дерева рейдов;
- push in-app уведомлений;
- обновления голосований гильдии;
- изменения календарных событий;
- рулетка аукциона гильдии (комната `guild:{id}:auction`).

## Стек

- **Fastify 5** — HTTP-сервер для broadcast hooks
- **Socket.IO** — WebSocket / long-polling
- Порт по умолчанию: **3007**

## Архитектура

```
Laravel Action → HTTP POST → socket_server → io.to(room).emit(...)
                                    ↑
Browser ← Socket.IO ← nginx/Vite proxy /socket.io
```

Laravel **не** пишет в Socket.IO напрямую — только HTTP-хуки на `socket_server`.

## Структура файлов

```
socket_server/
├── src/
│   ├── index.js                 # Точка входа, CORS
│   ├── plugins/socketio.js      # Регистрация Socket.IO
│   ├── routes/index.js          # HTTP broadcast endpoints
│   ├── raidSocketHandler.js
│   ├── notificationSocketHandler.js
│   ├── guildPollSocketHandler.js
│   ├── guildEventSocketHandler.js
│   └── auctionSocketHandler.js
└── package.json
```

## HTTP Broadcast Hooks

Все — `POST`, тело JSON. Базовый URL в Docker: `http://socket-server-nodejs:3007`.

| Endpoint | Тело | Событие клиенту |
|----------|------|-----------------|
| `/raids/broadcast-updated` | `guildId`, `raidId`, `raid` | Обновление открытого рейда |
| `/raids-tree/broadcast-updated` | `guildId`, `payload?` | Дерево рейдов |
| `/notifications/broadcast-created` | `userId`, `notification`, `unreadCount` | Новое уведомление |
| `/notifications/broadcast-deleted` | `userId`, `ids[]`, `unreadCount` | Удаление |
| `/notifications/broadcast-read` | `userId`, `id`, `readAt`, `unreadCount` | Прочитано |
| `/guild-polls/broadcast-changed` | `guildId`, `pollId` | Опрос изменён |
| `/guild-polls/broadcast-deleted` | `guildId`, `pollId` | Опрос удалён |
| `/guild-events/broadcast-changed` | `guildId`, `eventId` | Событие календаря |

Ответ при успехе: `{ ok: true }`, при ошибке валидации: **400** `{ ok: false }`.

## Комнаты Socket.IO

| Комната | Подписка |
|---------|----------|
| `guild:{guildId}:raid:{raidId}` | Участники экрана рейда |
| `guild:{guildId}:raids` | Дерево рейдов |
| `user:{userId}` | Уведомления пользователя |
| `guild:{guildId}:polls` | Опросы гильдии |
| `guild:{guildId}:events` | Календарь |
| `guild:{guildId}:auction` | Рулетка аукциона |

Клиент присоединяется к комнатам после подключения (см. `shared/lib` на фронте).

## Проксирование

### Development

- Vite (`gg-frontend`) проксирует `/socket.io` → `VITE_SOCKET_DEV_PROXY_TARGET` (по умолчанию `http://socket-server-nodejs:3007`)
- Браузер ходит на тот же origin, что и фронт

### Production

- Nginx проксирует `/socket.io` на `socket_server:3007`

## Запуск

```bash
# Через Docker (рекомендуется)
docker compose up socket-server-nodejs

# Локально
cd socket_server && npm install && npm run dev
```

Контейнер при старте выполняет `npm install && npm run dev` (nodemon).

## Отладка

- `GET /` на socket_server → `{ hello: 'world' }`
- `POST /test`, `/send-action` — тестовый emit `action` (для отладки)

## Связанные документы

- [08-uvedomleniya.md](08-uvedomleniya.md) — цепочка уведомлений
- [03-infrastruktura.md](03-infrastruktura.md) — порты Docker
