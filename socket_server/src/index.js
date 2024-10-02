import Fastify from 'fastify';
import cors from '@fastify/cors';
import socketioPlugin from './plugins/socketio.js';
import routes from './routes/index.js';

const app = Fastify({logger: true});
// Регистрация плагина CORS
app.register(cors, {
  origin: '*', // Разрешить все источники
  methods: ['GET', 'POST', 'PUT', 'DELETE'], // Разрешенные методы
  allowedHeaders: ['Content-Type', 'Authorization'], // Разрешенные заголовки
});

// Регистрация плагина Socket.IO
app.register(socketioPlugin);

// Регистрация HTTP маршрутов
app.register(routes);


// app.get('/', (req, reply) => {
//     return { message: 'ok!!!' };
// });

try{
    app.listen({ port: 3007, host: '0.0.0.0' });
} catch (error) {
    app.log.error(error);
    process.exit(1);
}
