import { emitRaidUpdated } from '../raidSocketHandler.js';

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

    // Добавьте здесь другие HTTP маршруты
};

export default routes;
