function guildAuctionsRoom(guildId) {
  return `guild:${guildId}:auctions`;
}

function normalizeId(raw) {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function registerGuildAuctionSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('guild-auctions:join', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.join(guildAuctionsRoom(guildId));
    });

    socket.on('guild-auctions:leave', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      if (guildId === null) return;
      socket.leave(guildAuctionsRoom(guildId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (guild-auctions)');
      }
    });
  });
}

export function emitGuildAuctionChanged(io, guildId, lotId) {
  io.to(guildAuctionsRoom(guildId)).emit('guild-auction:changed', {
    guildId,
    lotId,
  });
}
