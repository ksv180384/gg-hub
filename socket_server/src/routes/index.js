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

    // Добавьте здесь другие HTTP маршруты
};

export default routes;
