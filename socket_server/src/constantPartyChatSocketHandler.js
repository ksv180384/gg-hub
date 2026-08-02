import axios from 'axios';

const DEFAULT_AUTH_URL = 'http://gg-nginx/api/v1/constant-party-chat/socket-auth';

function constantPartyChatRoom(partyId) {
  return `constant-party:${partyId}:chat`;
}

function normalizeId(raw) {
  const value = Number(raw);
  if (!Number.isFinite(value) || value <= 0) return null;
  return value;
}

async function resolveIdentity(token) {
  if (typeof token !== 'string' || token.length < 40 || token.length > 255) {
    return null;
  }

  try {
    const response = await axios.post(
      process.env.CONSTANT_PARTY_CHAT_AUTH_URL ?? DEFAULT_AUTH_URL,
      { token },
      { timeout: 2000 },
    );
    const data = response.data?.data;
    const partyId = normalizeId(data?.party_id);
    const characterId = normalizeId(data?.character?.id);
    if (partyId === null || characterId === null) return null;

    return {
      partyId,
      character: {
        id: characterId,
        name: typeof data.character.name === 'string' ? data.character.name : 'Персонаж',
        avatarUrl: typeof data.character.avatar_url === 'string' ? data.character.avatar_url : null,
      },
    };
  } catch {
    return null;
  }
}

export function registerConstantPartyChatSocketHandlers(io, log = console) {
  const presence = new Map();

  function emitPresence(partyId) {
    const partyPresence = presence.get(partyId);
    const characters = partyPresence
      ? Array.from(partyPresence.values()).map((entry) => entry.character)
      : [];

    io.to(constantPartyChatRoom(partyId)).emit('constant-party-chat:presence', {
      partyId,
      characters,
    });
  }

  function leaveParty(socket, partyId) {
    const joinedParties = socket.data.constantPartyChats;
    const characterId = joinedParties?.get(partyId);
    if (!characterId) return;

    joinedParties.delete(partyId);
    socket.leave(constantPartyChatRoom(partyId));

    const partyPresence = presence.get(partyId);
    const entry = partyPresence?.get(characterId);
    if (entry) {
      entry.socketIds.delete(socket.id);
      if (entry.socketIds.size === 0) {
        partyPresence.delete(characterId);
      }
    }
    if (partyPresence?.size === 0) {
      presence.delete(partyId);
    }

    emitPresence(partyId);
  }

  io.on('connection', (socket) => {
    socket.data.constantPartyChats = new Map();

    socket.on('constant-party-chat:join', async (payload) => {
      const identity = await resolveIdentity(payload?.token);
      if (!identity || !socket.connected) {
        socket.emit('constant-party-chat:auth-error');
        return;
      }

      const { partyId, character } = identity;
      const previousCharacterId = socket.data.constantPartyChats.get(partyId);
      if (previousCharacterId === character.id) {
        emitPresence(partyId);
        return;
      }
      if (previousCharacterId) {
        leaveParty(socket, partyId);
      }

      socket.join(constantPartyChatRoom(partyId));
      socket.data.constantPartyChats.set(partyId, character.id);

      let partyPresence = presence.get(partyId);
      if (!partyPresence) {
        partyPresence = new Map();
        presence.set(partyId, partyPresence);
      }

      let entry = partyPresence.get(character.id);
      if (!entry) {
        entry = { character, socketIds: new Set() };
        partyPresence.set(character.id, entry);
      }
      entry.character = character;
      entry.socketIds.add(socket.id);

      emitPresence(partyId);
    });

    socket.on('constant-party-chat:leave', (payload) => {
      const partyId = normalizeId(payload?.partyId);
      if (partyId === null) return;
      leaveParty(socket, partyId);
    });

    socket.on('disconnect', () => {
      for (const partyId of Array.from(socket.data.constantPartyChats.keys())) {
        leaveParty(socket, partyId);
      }

      if (typeof log.info === 'function') {
        log.info({ id: socket.id }, 'socket client disconnected (constant-party-chat)');
      }
    });
  });
}

export function emitConstantPartyChatMessageCreated(io, partyId, message) {
  io.to(constantPartyChatRoom(partyId)).emit('constant-party-chat:message-created', {
    partyId,
    message,
  });
}

export function emitConstantPartyChatReceiptsChanged(io, partyId, messages) {
  io.to(constantPartyChatRoom(partyId)).emit('constant-party-chat:receipts-changed', {
    partyId,
    messages,
  });
}

export function emitConstantPartyChatMessageDeleted(io, partyId, messageId) {
  io.to(constantPartyChatRoom(partyId)).emit('constant-party-chat:message-deleted', {
    partyId,
    messageId,
  });
}
