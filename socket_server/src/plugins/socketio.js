import fastifyPlugin from 'fastify-plugin';
import fastifySocketIO from 'fastify-socket.io';
import { registerAuctionSocketHandlers } from '../auctionSocketHandler.js';

const socketioPlugin = async (fastify, options) => {
    fastify.register(fastifySocketIO);

    fastify.ready(err => {
        if (err) throw err;

        registerAuctionSocketHandlers(fastify.io, fastify.log);
    });
};

export default fastifyPlugin(socketioPlugin);
