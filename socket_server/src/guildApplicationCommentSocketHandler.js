function guildApplicationCommentsRoom(guildId, applicationId) {
  return `guild:${guildId}:applications:${applicationId}:comments`;
}

function normalizeId(raw) {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function registerGuildApplicationCommentSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('guild-application-comments:join', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      const applicationId = normalizeId(payload?.applicationId);
      if (guildId === null || applicationId === null) return;
      socket.join(guildApplicationCommentsRoom(guildId, applicationId));
    });

    socket.on('guild-application-comments:leave', (payload) => {
      const guildId = normalizeId(payload?.guildId);
      const applicationId = normalizeId(payload?.applicationId);
      if (guildId === null || applicationId === null) return;
      socket.leave(guildApplicationCommentsRoom(guildId, applicationId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (guild-application-comments)');
      }
    });
  });
}

export function emitGuildApplicationCommentChanged(io, guildId, applicationId, commentId, action) {
  io.to(guildApplicationCommentsRoom(guildId, applicationId)).emit('guild-application-comment:changed', {
    guildId,
    applicationId,
    commentId,
    action,
  });
}
