/**
 * Пользовательские комнаты оповещений: push новых/удалённых/прочитанных событий конкретному пользователю.
 *
 * Сервер НЕ хранит состояние — backend является источником истины.
 * Socket server лишь доставляет клиенту события, полученные от backend по HTTP.
 *
 * Простая модель без auth (аналогично комнатам аукциона): клиент сам сообщает свой userId при `notifications:join`.
 * Это не защищает от чтения чужих уведомлений на уровне протокола и рассчитано на доверенную среду,
 * но backend всё равно выдаёт уведомления только своему пользователю через авторизованный API.
 */

function userNotificationsRoom(userId) {
  return `user:${userId}:notifications`;
}

function normalizeUserId(raw) {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function registerNotificationSocketHandlers(io, log = console) {
  io.on('connection', (socket) => {
    socket.on('notifications:join', (payload) => {
      const userId = normalizeUserId(payload?.userId);
      if (userId === null) return;
      socket.join(userNotificationsRoom(userId));
    });

    socket.on('notifications:leave', (payload) => {
      const userId = normalizeUserId(payload?.userId);
      if (userId === null) return;
      socket.leave(userNotificationsRoom(userId));
    });

    socket.on('disconnect', () => {
      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (notifications)');
      }
    });
  });
}

/**
 * Пришло новое уведомление пользователю.
 * @param {import('socket.io').Server} io
 * @param {number} userId
 * @param {object} notification — объект из NotificationResource
 * @param {number} unreadCount — актуальный счётчик непрочитанных
 */
export function emitNotificationCreated(io, userId, notification, unreadCount) {
  io.to(userNotificationsRoom(userId)).emit('notification:created', {
    userId,
    notification,
    unreadCount,
  });
}

/**
 * Удалено одно или несколько уведомлений пользователя.
 * @param {import('socket.io').Server} io
 * @param {number} userId
 * @param {number[]} ids
 * @param {number} unreadCount
 */
export function emitNotificationsDeleted(io, userId, ids, unreadCount) {
  io.to(userNotificationsRoom(userId)).emit('notification:deleted', {
    userId,
    ids,
    unreadCount,
  });
}

/**
 * Пользователь пометил уведомление как прочитанное (возможно, в другой вкладке).
 * @param {import('socket.io').Server} io
 * @param {number} userId
 * @param {number} id
 * @param {string} readAt — ISO 8601
 * @param {number} unreadCount
 */
export function emitNotificationRead(io, userId, id, readAt, unreadCount) {
  io.to(userNotificationsRoom(userId)).emit('notification:read', {
    userId,
    id,
    readAt,
    unreadCount,
  });
}
