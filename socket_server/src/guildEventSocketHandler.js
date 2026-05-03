/**
 * Комнаты календаря гильдии: push изменений участников/отказов событий участникам, открывшим календарь.
 *
 * Модель:
 * - Комната `guild:{id}:events` содержит всех участников гильдии, подключённых сокетом.
 * - Клиент сам сообщает guildId при `guild-events:join` / `guild-events:leave`.
 * - Источник истины — backend API; socket лишь ретранслирует уведомления об изменениях.
 */

function guildEventsRoom(guildId) {
  return `guild:${guildId}:events`;
}

function normalizeId(raw) {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function registerGuildEventSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('guild-events:join', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.join(guildEventsRoom(guildId));
    });

    socket.on('guild-events:leave', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.leave(guildEventsRoom(guildId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (guild-events)');
      }
    });
  });
}

/**
 * Обновился состав участников/отказов в событии (или другое изменение, влияющее на карточку).
 *
 * @param {import('socket.io').Server} io
 * @param {number} guildId
 * @param {number} eventId
 */
export function emitGuildEventChanged(io, guildId, eventId) {
  io.to(guildEventsRoom(guildId)).emit('guild-event:changed', {
    guildId,
    eventId,
  });
}

