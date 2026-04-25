/**
 * Socket.IO-подписка на события голосований гильдий, в которых состоит пользователь.
 *
 * Поведение подключения аналогично `useNotificationsSocket`:
 * - Без `VITE_SOCKET_URL` (или пусто) — same-origin через `/socket.io` (проксирует nginx/vite).
 * - `VITE_SOCKET_URL=off` — не подключаться.
 *
 * Сервер — источник истины (backend API). Socket доставляет только факт изменения
 * (guildId + pollId), клиент подтягивает актуальные данные через REST.
 */

import { io, type Socket } from 'socket.io-client';
import {
  onMounted,
  onUnmounted,
  shallowRef,
  watch,
  type Ref,
} from 'vue';

export interface GuildPollChangedEvent {
  guildId: number;
  pollId: number;
}

export interface GuildPollDeletedEvent {
  guildId: number;
  pollId: number;
}

export interface UseGuildPollsSocketOptions {
  /** Список id гильдий, на которые подписаться. При изменении — перекомнатим. */
  guildIds: Ref<number[]>;
  onChanged?: (event: GuildPollChangedEvent) => void;
  onDeleted?: (event: GuildPollDeletedEvent) => void;
}

function readSocketUrl(): { configured: boolean; url: string | undefined } {
  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  const syncOff = rawEnv === 'off' || rawEnv === 'false';
  if (syncOff) return { configured: false, url: undefined };
  return { configured: true, url: rawEnv.length > 0 ? rawEnv : undefined };
}

function normalizeIds(raw: number[] | undefined | null): number[] {
  if (!Array.isArray(raw)) return [];
  const out: number[] = [];
  const seen = new Set<number>();
  for (const x of raw) {
    const n = Number(x);
    if (!Number.isFinite(n) || n <= 0) continue;
    if (seen.has(n)) continue;
    seen.add(n);
    out.push(n);
  }
  return out;
}

function arraysEqual(a: number[], b: number[]): boolean {
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
  return true;
}

export function useGuildPollsSocket(options: UseGuildPollsSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const joinedIds = shallowRef<number[]>([]);
  const { configured, url } = readSocketUrl();

  function join(guildId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0) return;
    s.emit('guild-polls:join', { guildId });
  }

  function leave(guildId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0) return;
    s.emit('guild-polls:leave', { guildId });
  }

  function syncRooms(next: number[]) {
    const prev = joinedIds.value;
    if (arraysEqual(prev, next)) return;
    for (const id of prev) {
      if (!next.includes(id)) leave(id);
    }
    for (const id of next) {
      if (!prev.includes(id)) join(id);
    }
    joinedIds.value = [...next];
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
      const ids = normalizeIds(options.guildIds.value);
      joinedIds.value = [];
      syncRooms(ids);
    });

    s.on('guild-poll:changed', (event: GuildPollChangedEvent) => {
      if (!event) return;
      const gid = Number(event.guildId);
      const pid = Number(event.pollId);
      if (!Number.isFinite(gid) || gid <= 0) return;
      if (!Number.isFinite(pid) || pid <= 0) return;
      if (!joinedIds.value.includes(gid)) return;
      options.onChanged?.({ guildId: gid, pollId: pid });
    });

    s.on('guild-poll:deleted', (event: GuildPollDeletedEvent) => {
      if (!event) return;
      const gid = Number(event.guildId);
      const pid = Number(event.pollId);
      if (!Number.isFinite(gid) || gid <= 0) return;
      if (!Number.isFinite(pid) || pid <= 0) return;
      if (!joinedIds.value.includes(gid)) return;
      options.onDeleted?.({ guildId: gid, pollId: pid });
    });
  });

  onUnmounted(() => {
    for (const id of joinedIds.value) leave(id);
    joinedIds.value = [];
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => normalizeIds(options.guildIds.value),
    (ids) => {
      syncRooms(ids);
    },
    { deep: false }
  );

  return {
    socketConfigured: configured,
  };
}
