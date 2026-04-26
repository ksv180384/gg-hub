import fastifyPlugin from 'fastify-plugin';
import fastifySocketIO from 'fastify-socket.io';
import { registerAuctionSocketHandlers } from '../auctionSocketHandler.js';
import { registerRaidSocketHandlers } from '../raidSocketHandler.js';
import { registerNotificationSocketHandlers } from '../notificationSocketHandler.js';
import { registerGuildPollSocketHandlers } from '../guildPollSocketHandler.js';
import { registerGuildEventSocketHandlers } from '../guildEventSocketHandler.js';

const socketioPlugin = async (fastify, options) => {
    fastify.register(fastifySocketIO);

    fastify.ready(err => {
        if (err) throw err;

        registerAuctionSocketHandlers(fastify.io, fastify.log);
        registerRaidSocketHandlers(fastify.io, fastify.log);
        registerNotificationSocketHandlers(fastify.io, fastify.log);
        registerGuildPollSocketHandlers(fastify.io, fastify.log);
        registerGuildEventSocketHandlers(fastify.io, fastify.log);
    });
};

export default fastifyPlugin(socketioPlugin);
