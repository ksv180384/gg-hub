/**
 * Комнаты голосований гильдии: push изменений списка/состояния голосований участникам.
 *
 * Модель:
 * - Комната `guild:{id}:polls` содержит всех участников гильдии, подключённых сокетом.
 * - Клиент сам сообщает свои guildIds при `guild-polls:join` / `guild-polls:leave`.
 * - Источник истины — backend API; socket лишь ретранслирует уведомления об изменениях.
 *
 * Простая модель без auth (аналогично комнатам аукциона/оповещений): не защищает от
 * подписки на чужую гильдию на уровне протокола, но backend отдаёт голосования только
 * участникам через авторизованный API.
 */

function guildPollsRoom(guildId) {
  return `guild:${guildId}:polls`;
}

function normalizeId(raw) {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function registerGuildPollSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('guild-polls:join', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.join(guildPollsRoom(guildId));
    });

    socket.on('guild-polls:leave', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.leave(guildPollsRoom(guildId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (guild-polls)');
      }
    });
  });
}

/**
 * Голосование в гильдии создано/обновлено (редактирование, закрытие, сброс, изменение голосов).
 * Клиенты в комнате подтянут актуальное состояние запросом в backend.
 *
 * @param {import('socket.io').Server} io
 * @param {number} guildId
 * @param {number} pollId
 */
export function emitGuildPollChanged(io, guildId, pollId) {
  io.to(guildPollsRoom(guildId)).emit('guild-poll:changed', {
    guildId,
    pollId,
  });
}

/**
 * Голосование удалено.
 *
 * @param {import('socket.io').Server} io
 * @param {number} guildId
 * @param {number} pollId
 */
export function emitGuildPollDeleted(io, guildId, pollId) {
  io.to(guildPollsRoom(guildId)).emit('guild-poll:deleted', {
    guildId,
    pollId,
  });
}
