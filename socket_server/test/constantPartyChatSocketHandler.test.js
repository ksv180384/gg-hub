import assert from 'node:assert/strict';
import test from 'node:test';
import axios from 'axios';
import Fastify from 'fastify';
import {
  emitConstantPartyChatMessageCreated,
  registerConstantPartyChatSocketHandlers,
} from '../src/constantPartyChatSocketHandler.js';
import { requireInternalRequest } from '../src/internalRequestAuth.js';

class FakeIo {
  connectionHandlers = [];

  roomEvents = [];

  on(event, handler) {
    if (event === 'connection') this.connectionHandlers.push(handler);
  }

  connect(socket) {
    for (const handler of this.connectionHandlers) handler(socket);
  }

  to(room) {
    return {
      emit: (event, payload) => this.roomEvents.push({ room, event, payload }),
    };
  }
}

class FakeSocket {
  constructor(id) {
    this.id = id;
    this.data = {};
    this.connected = true;
    this.handlers = new Map();
    this.rooms = new Set();
    this.serverEvents = [];
  }

  on(event, handler) {
    this.handlers.set(event, handler);
  }

  emit(event, payload) {
    this.serverEvents.push({ event, payload });
  }

  join(room) {
    this.rooms.add(room);
  }

  leave(room) {
    this.rooms.delete(room);
  }

  async trigger(event, payload) {
    return this.handlers.get(event)?.(payload);
  }
}

test('private chat join requires a backend-validated token and deduplicates presence', async () => {
  const originalPost = axios.post;
  axios.post = async (_url, payload) => {
    if (payload.token !== 'valid-token-value-with-more-than-forty-characters') {
      throw new Error('Unauthorized');
    }
    return {
      data: {
        data: {
          party_id: 7,
          character: {
            id: 12,
            name: 'Member',
            avatar_url: null,
          },
        },
      },
    };
  };

  try {
    const io = new FakeIo();
    registerConstantPartyChatSocketHandlers(io, { info() {} });
    const first = new FakeSocket('first');
    const second = new FakeSocket('second');
    const invalid = new FakeSocket('invalid');
    io.connect(first);
    io.connect(second);
    io.connect(invalid);

    await invalid.trigger('constant-party-chat:join', { token: 'invalid-token-value-with-more-than-forty-characters' });
    assert.equal(invalid.rooms.size, 0);
    assert.equal(invalid.serverEvents.at(-1)?.event, 'constant-party-chat:auth-error');

    const token = 'valid-token-value-with-more-than-forty-characters';
    await first.trigger('constant-party-chat:join', { token });
    await second.trigger('constant-party-chat:join', { token });

    assert.equal(first.rooms.has('constant-party:7:chat'), true);
    assert.deepEqual(io.roomEvents.at(-1)?.payload.characters, [
      { id: 12, name: 'Member', avatarUrl: null },
    ]);

    await first.trigger('disconnect');
    assert.equal(io.roomEvents.at(-1)?.payload.characters.length, 1);
    await second.trigger('disconnect');
    assert.equal(io.roomEvents.at(-1)?.payload.characters.length, 0);
  } finally {
    axios.post = originalPost;
  }
});

test('backend broadcasts target only the requested party room', () => {
  const io = new FakeIo();
  emitConstantPartyChatMessageCreated(io, 8, { id: 31, body: 'Hello' });

  assert.deepEqual(io.roomEvents, [{
    room: 'constant-party:8:chat',
    event: 'constant-party-chat:message-created',
    payload: {
      partyId: 8,
      message: { id: 31, body: 'Hello' },
    },
  }]);
});

test('internal broadcast hook rejects invalid tokens and allows a valid token', async () => {
  const previousToken = process.env.SOCKET_SERVER_INTERNAL_TOKEN;
  process.env.SOCKET_SERVER_INTERNAL_TOKEN = 'expected-secret';

  const replies = [];
  const reply = {
    code(status) {
      replies.push(status);
      return this;
    },
    send() {},
  };

  try {
    await requireInternalRequest({ headers: {} }, reply);
    await requireInternalRequest({ headers: { 'x-socket-internal-token': 'incorrect' } }, reply);
    const result = await requireInternalRequest({
      headers: { 'x-socket-internal-token': 'expected-secret' },
    }, reply);

    assert.deepEqual(replies, [401, 401]);
    assert.equal(result, undefined);
  } finally {
    if (previousToken === undefined) delete process.env.SOCKET_SERVER_INTERNAL_TOKEN;
    else process.env.SOCKET_SERVER_INTERNAL_TOKEN = previousToken;
  }
});

test('valid internal token continues to the Fastify route handler', async () => {
  const previousToken = process.env.SOCKET_SERVER_INTERNAL_TOKEN;
  process.env.SOCKET_SERVER_INTERNAL_TOKEN = 'expected-secret';
  const fastify = Fastify();

  fastify.post('/internal', { preHandler: requireInternalRequest }, async () => ({ ok: true }));

  try {
    const response = await fastify.inject({
      method: 'POST',
      url: '/internal',
      headers: {
        'x-socket-internal-token': 'expected-secret',
      },
    });

    assert.equal(response.statusCode, 200);
    assert.deepEqual(response.json(), { ok: true });
  } finally {
    await fastify.close();
    if (previousToken === undefined) delete process.env.SOCKET_SERVER_INTERNAL_TOKEN;
    else process.env.SOCKET_SERVER_INTERNAL_TOKEN = previousToken;
  }
});
