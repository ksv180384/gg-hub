/**
 * Комнаты рейдов гильдии: пуш обновлений состава рейда.
 *
 * Сервер НЕ хранит состояние рейда — источником истины является backend API.
 * Socket сервер лишь доставляет событие всем клиентам, кто "смотрит" конкретный рейд.
 */

function raidRoom(guildId, raidId) {
  return `guild:${guildId}:raid:${raidId}`;
}

export function registerRaidSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('raid:join', (payload) => {
      const guildId = Number(payload?.guildId);
      const raidId = Number(payload?.raidId);
      if (!Number.isFinite(guildId) || guildId <= 0) return;
      if (!Number.isFinite(raidId) || raidId <= 0) return;
      socket.join(raidRoom(guildId, raidId));
    });

    socket.on('raid:leave', (payload) => {
      const guildId = Number(payload?.guildId);
      const raidId = Number(payload?.raidId);
      if (!Number.isFinite(guildId) || guildId <= 0) return;
      if (!Number.isFinite(raidId) || raidId <= 0) return;
      socket.leave(raidRoom(guildId, raidId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (raid)');
      }
    });
  });
}

export function emitRaidUpdated(io, guildId, raidId, raid) {
  io.to(raidRoom(guildId, raidId)).emit('raid:updated', {
    guildId,
    raidId,
    raid,
  });
}

