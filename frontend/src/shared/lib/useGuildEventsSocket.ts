/**
 * Socket.IO-подписка на события календаря гильдии.
 *
 * Модель как у `useGuildPollsSocket`:
 * - Клиент сам сообщает guildId при join/leave.
 * - Socket доставляет только факт изменения (guildId + eventId), клиент подтягивает актуальные данные через REST.
 */

import { io, type Socket } from 'socket.io-client';
import { onMounted, onUnmounted, shallowRef, watch, type Ref } from 'vue';

export interface GuildEventChangedEvent {
  guildId: number;
  eventId: number;
}

export interface UseGuildEventsSocketOptions {
  guildId: Ref<number | null>;
  onChanged?: (event: GuildEventChangedEvent) => void;
}

function readSocketUrl(): { configured: boolean; url: string | undefined } {
  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  const syncOff = rawEnv === 'off' || rawEnv === 'false';
  if (syncOff) return { configured: false, url: undefined };
  return { configured: true, url: rawEnv.length > 0 ? rawEnv : undefined };
}

function normalizeId(raw: unknown): number | null {
  const n = Number(raw);
  if (!Number.isFinite(n) || n <= 0) return null;
  return n;
}

export function useGuildEventsSocket(options: UseGuildEventsSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const joinedId = shallowRef<number | null>(null);
  const { configured, url } = readSocketUrl();

  function join(guildId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0) return;
    s.emit('guild-events:join', { guildId });
  }

  function leave(guildId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0) return;
    s.emit('guild-events:leave', { guildId });
  }

  function syncRoom(nextId: number | null) {
    const prev = joinedId.value;
    if (prev && prev > 0) leave(prev);
    joinedId.value = null;
    if (nextId && nextId > 0) {
      join(nextId);
      joinedId.value = nextId;
    }
  }

  onMounted(() => {
    if (!configured || import.meta.env.SSR) return;

    const s = io(url, {
      transports: ['websocket', 'polling'],
      path: '/socket.io',
      autoConnect: true,
    });
    socketRef.value = s;

    s.on('connect', () => {
      syncRoom(normalizeId(options.guildId.value));
    });

    s.on('guild-event:changed', (event: GuildEventChangedEvent) => {
      if (!event) return;
      const gid = normalizeId(event.guildId);
      const eid = normalizeId(event.eventId);
      if (!gid || !eid) return;
      if (joinedId.value !== gid) return;
      options.onChanged?.({ guildId: gid, eventId: eid });
    });
  });

  onUnmounted(() => {
    const prev = joinedId.value;
    if (prev && prev > 0) leave(prev);
    joinedId.value = null;
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => normalizeId(options.guildId.value),
    (gid) => {
      syncRoom(gid);
    }
  );

  return {
    socketConfigured: configured,
  };
}

