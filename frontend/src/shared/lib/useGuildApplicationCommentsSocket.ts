import type { Socket } from 'socket.io-client';
import { onMounted, onUnmounted, shallowRef, watch, type Ref } from 'vue';

export interface GuildApplicationCommentChangedEvent {
  guildId: number;
  applicationId: number;
  commentId: number;
  action: string;
}

export interface UseGuildApplicationCommentsSocketOptions {
  guildId: Ref<number | null>;
  applicationId: Ref<number | null>;
  onChanged?: (event: GuildApplicationCommentChangedEvent) => void;
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

export function useGuildApplicationCommentsSocket(options: UseGuildApplicationCommentsSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const joinedRoom = shallowRef<{ guildId: number; applicationId: number } | null>(null);
  const { configured, url } = readSocketUrl();

  function join(guildId: number, applicationId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0 || applicationId <= 0) return;
    s.emit('guild-application-comments:join', { guildId, applicationId });
  }

  function leave(guildId: number, applicationId: number) {
    const s = socketRef.value;
    if (!s?.connected || guildId <= 0 || applicationId <= 0) return;
    s.emit('guild-application-comments:leave', { guildId, applicationId });
  }

  function syncRoom(guildId: number | null, applicationId: number | null) {
    const prev = joinedRoom.value;
    if (prev) leave(prev.guildId, prev.applicationId);
    joinedRoom.value = null;
    if (guildId && applicationId) {
      join(guildId, applicationId);
      joinedRoom.value = { guildId, applicationId };
    }
  }

  onMounted(() => {
    if (!configured || import.meta.env.SSR) return;

    void import('socket.io-client').then(({ io }) => {
      const socket = io(url, {
        transports: ['websocket', 'polling'],
        path: '/socket.io',
        autoConnect: true,
      });
      socketRef.value = socket;

      socket.on('connect', () => {
        syncRoom(normalizeId(options.guildId.value), normalizeId(options.applicationId.value));
      });

      socket.on('guild-application-comment:changed', (event: GuildApplicationCommentChangedEvent) => {
        if (!event) return;
        const guildId = normalizeId(event.guildId);
        const applicationId = normalizeId(event.applicationId);
        const commentId = normalizeId(event.commentId);
        if (!guildId || !applicationId || !commentId) return;
        const joined = joinedRoom.value;
        if (!joined || joined.guildId !== guildId || joined.applicationId !== applicationId) return;
        options.onChanged?.({
          guildId,
          applicationId,
          commentId,
          action: typeof event.action === 'string' ? event.action : 'changed',
        });
      });
    });
  });

  onUnmounted(() => {
    const prev = joinedRoom.value;
    if (prev) leave(prev.guildId, prev.applicationId);
    joinedRoom.value = null;
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => [normalizeId(options.guildId.value), normalizeId(options.applicationId.value)] as const,
    ([guildId, applicationId]) => {
      syncRoom(guildId, applicationId);
    }
  );

  return {
    socketConfigured: configured,
  };
}
