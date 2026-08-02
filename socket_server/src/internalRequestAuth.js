import { timingSafeEqual } from 'node:crypto';

export async function requireInternalRequest(request, reply) {
  const expected = process.env.SOCKET_SERVER_INTERNAL_TOKEN ?? '';
  const received = request.headers['x-socket-internal-token'];
  if (!expected || typeof received !== 'string') {
    return reply.code(401).send({ ok: false });
  }

  const expectedBuffer = Buffer.from(expected);
  const receivedBuffer = Buffer.from(received);
  if (
    expectedBuffer.length !== receivedBuffer.length
    || !timingSafeEqual(expectedBuffer, receivedBuffer)
  ) {
    return reply.code(401).send({ ok: false });
  }
}
