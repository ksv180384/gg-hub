import fastifyPlugin from 'fastify-plugin';
import fastifySocketIO from 'fastify-socket.io';

const socketioPlugin = async (fastify, options) => {
    fastify.register(fastifySocketIO);

    fastify.ready(err => {
        if (err) throw err;

        fastify.io.on('connection', socket => {
            console.log('Новый клиент подключен');

            socket.on('message', msg => {
                console.log('Сообщение от клиента:', msg);
                socket.emit('message', `Вы сказали: ${msg}`);
            });

            socket.on('disconnect', () => {
                console.log('Клиент отключен');
            });

        });
    });
};

export default fastifyPlugin(socketioPlugin);
