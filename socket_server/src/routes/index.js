import { emitRaidUpdated, emitRaidsTreeUpdated } from '../raidSocketHandler.js';
import {
  emitNotificationCreated,
  emitNotificationsDeleted,
  emitNotificationRead,
} from '../notificationSocketHandler.js';
import {
  emitGuildPollChanged,
  emitGuildPollDeleted,
} from '../guildPollSocketHandler.js';
import { emitGuildEventChanged } from '../guildEventSocketHandler.js';

const routes = async (fastify, options) => {
    fastify.get('/', async (request, reply) => {
        return { hello: 'world' };
    });

  fastify.post('/test', async (request, reply) => {
    // console.log(response.body);
    fastify.io.emit('action', request.body);
    // return { hello: 'world !!!' };
  });

  fastify.post('/send-action', async (request, reply) => {
    fastify.io.emit('action', request.body);
  });

  /**
   * Backend hook: обновился рейд (состав/слоты) — пушим всем, кто открыл этот рейд.
   * body: { guildId: number, raidId: number, raid: object }
   */
  fastify.post('/raids/broadcast-updated', async (request, reply) => {
    const guildId = Number(request.body?.guildId);
    const raidId = Number(request.body?.raidId);
    const raid = request.body?.raid ?? null;
    if (!Number.isFinite(guildId) || guildId <= 0) return reply.code(400).send({ ok: false });
    if (!Number.isFinite(raidId) || raidId <= 0) return reply.code(400).send({ ok: false });
    emitRaidUpdated(fastify.io, guildId, raidId, raid);
    return { ok: true };
  });

  /**
   * Backend hook: обновилось дерево рейдов гильдии (создание/редактирование/удаление/перенос/сортировка).
   * body: { guildId: number, payload?: any }
   */
  fastify.post('/raids-tree/broadcast-updated', async (request, reply) => {
    const guildId = Number(request.body?.guildId);
    const payload = request.body?.payload ?? null;
    if (!Number.isFinite(guildId) || guildId <= 0) return reply.code(400).send({ ok: false });
    emitRaidsTreeUpdated(fastify.io, guildId, payload);
    return { ok: true };
  });

  /**
   * Backend hook: создано новое уведомление — пушим пользователю.
   * body: { userId: number, notification: object, unreadCount: number }
   */
  fastify.post('/notifications/broadcast-created', async (request, reply) => {
    const userId = Number(request.body?.userId);
    const unreadCount = Number(request.body?.unreadCount ?? 0);
    const notification = request.body?.notification ?? null;
    if (!Number.isFinite(userId) || userId <= 0) return reply.code(400).send({ ok: false });
    if (!notification || typeof notification !== 'object') return reply.code(400).send({ ok: false });
    emitNotificationCreated(fastify.io, userId, notification, Number.isFinite(unreadCount) ? unreadCount : 0);
    return { ok: true };
  });

  /**
   * Backend hook: удалены одно или несколько уведомлений пользователя.
   * body: { userId: number, ids: number[], unreadCount: number }
   */
  fastify.post('/notifications/broadcast-deleted', async (request, reply) => {
    const userId = Number(request.body?.userId);
    const unreadCount = Number(request.body?.unreadCount ?? 0);
    const rawIds = Array.isArray(request.body?.ids) ? request.body.ids : [];
    const ids = rawIds
      .map((x) => Number(x))
      .filter((x) => Number.isFinite(x) && x > 0);
    if (!Number.isFinite(userId) || userId <= 0) return reply.code(400).send({ ok: false });
    if (ids.length === 0) return reply.code(400).send({ ok: false });
    emitNotificationsDeleted(fastify.io, userId, ids, Number.isFinite(unreadCount) ? unreadCount : 0);
    return { ok: true };
  });

  /**
   * Backend hook: уведомление пользователя помечено прочитанным.
   * body: { userId: number, id: number, readAt: string, unreadCount: number }
   */
  fastify.post('/notifications/broadcast-read', async (request, reply) => {
    const userId = Number(request.body?.userId);
    const id = Number(request.body?.id);
    const unreadCount = Number(request.body?.unreadCount ?? 0);
    const readAt = typeof request.body?.readAt === 'string' ? request.body.readAt : '';
    if (!Number.isFinite(userId) || userId <= 0) return reply.code(400).send({ ok: false });
    if (!Number.isFinite(id) || id <= 0) return reply.code(400).send({ ok: false });
    if (!readAt) return reply.code(400).send({ ok: false });
    emitNotificationRead(fastify.io, userId, id, readAt, Number.isFinite(unreadCount) ? unreadCount : 0);
    return { ok: true };
  });

  /**
   * Backend hook: голосование гильдии создано/обновлено (редактирование/закрытие/сброс/голоса).
   * body: { guildId: number, pollId: number }
   */
  fastify.post('/guild-polls/broadcast-changed', async (request, reply) => {
    const guildId = Number(request.body?.guildId);
    const pollId = Number(request.body?.pollId);
    if (!Number.isFinite(guildId) || guildId <= 0) return reply.code(400).send({ ok: false });
    if (!Number.isFinite(pollId) || pollId <= 0) return reply.code(400).send({ ok: false });
    emitGuildPollChanged(fastify.io, guildId, pollId);
    return { ok: true };
  });

  /**
   * Backend hook: голосование гильдии удалено.
   * body: { guildId: number, pollId: number }
   */
  fastify.post('/guild-polls/broadcast-deleted', async (request, reply) => {
    const guildId = Number(request.body?.guildId);
    const pollId = Number(request.body?.pollId);
    if (!Number.isFinite(guildId) || guildId <= 0) return reply.code(400).send({ ok: false });
    if (!Number.isFinite(pollId) || pollId <= 0) return reply.code(400).send({ ok: false });
    emitGuildPollDeleted(fastify.io, guildId, pollId);
    return { ok: true };
  });

  /**
   * Backend hook: календарное событие гильдии обновлено (например, отказ/участники).
   * body: { guildId: number, eventId: number }
   */
  fastify.post('/guild-events/broadcast-changed', async (request, reply) => {
    const guildId = Number(request.body?.guildId);
    const eventId = Number(request.body?.eventId);
    if (!Number.isFinite(guildId) || guildId <= 0) return reply.code(400).send({ ok: false });
    if (!Number.isFinite(eventId) || eventId <= 0) return reply.code(400).send({ ok: false });
    emitGuildEventChanged(fastify.io, guildId, eventId);
    return { ok: true };
  });
};

export default routes;
