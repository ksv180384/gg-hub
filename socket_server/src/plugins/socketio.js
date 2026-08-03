import fastifyPlugin from 'fastify-plugin';
import fastifySocketIO from 'fastify-socket.io';
import { registerRouletteSocketHandlers } from '../rouletteSocketHandler.js';
import { registerRaidSocketHandlers } from '../raidSocketHandler.js';
import { registerNotificationSocketHandlers } from '../notificationSocketHandler.js';
import { registerGuildPollSocketHandlers } from '../guildPollSocketHandler.js';
import { registerGuildEventSocketHandlers } from '../guildEventSocketHandler.js';
import { registerGuildAuctionSocketHandlers } from '../guildAuctionSocketHandler.js';
import { registerGuildApplicationCommentSocketHandlers } from '../guildApplicationCommentSocketHandler.js';
import { isFeatureEnabled } from '../featureFlags.js';

const socketioPlugin = async (fastify, options) => {
    fastify.register(fastifySocketIO);

    fastify.addHook('onReady', async () => {
        registerRouletteSocketHandlers(fastify.io, fastify.log);
        registerRaidSocketHandlers(fastify.io, fastify.log);
        registerNotificationSocketHandlers(fastify.io, fastify.log);
        registerGuildPollSocketHandlers(fastify.io, fastify.log);
        registerGuildEventSocketHandlers(fastify.io, fastify.log);
        registerGuildAuctionSocketHandlers(fastify.io, fastify.log);
        registerGuildApplicationCommentSocketHandlers(fastify.io, fastify.log);

        if (isFeatureEnabled('CONSTANT_PARTY_CHAT_ENABLED')) {
            const { registerConstantPartyChatSocketHandlers } = await import(
                '../constantPartyChatSocketHandler.js'
            );
            registerConstantPartyChatSocketHandlers(fastify.io, fastify.log);
        }
    });
};

export default fastifyPlugin(socketioPlugin);
