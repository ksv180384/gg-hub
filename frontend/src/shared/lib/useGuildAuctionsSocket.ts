import type { Socket } from 'socket.io-client';
import { onMounted, onUnmounted, shallowRef, watch, type Ref } from 'vue';

export interface GuildAuctionChangedEvent {
  guildId: number;
  lotId: number;
}

export interface UseGuildAuctionsSocketOptions {
  guildIds: Ref<number[]>;
  onChanged?: (event: GuildAuctionChangedEvent) => void;
}

function readSocketUrl(): { configured: boolean; url: string | undefined } {
  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  if (rawEnv === 'off' || rawEnv === 'false') return { configured: false, url: undefined };
  return { configured: true, url: rawEnv.length > 0 ? rawEnv : undefined };
}

function normalizeIds(raw: number[] | undefined | null): number[] {
  if (!Array.isArray(raw)) return [];
  const out: number[] = [];
  const seen = new Set<number>();
  for (const x of raw) {
    const n = Number(x);
    if (!Number.isFinite(n) || n <= 0 || seen.has(n)) continue;
    seen.add(n);
    out.push(n);
  }
  return out;
}

export function useGuildAuctionsSocket(options: UseGuildAuctionsSocketOptions) {
  const socketRef = shallowRef<Socket | null>(null);
  const joinedIds = shallowRef<number[]>([]);
  const { configured, url } = readSocketUrl();

  function join(guildId: number) {
    if (!socketRef.value?.connected) return;
    socketRef.value.emit('guild-auctions:join', { guildId });
  }

  function leave(guildId: number) {
    if (!socketRef.value?.connected) return;
    socketRef.value.emit('guild-auctions:leave', { guildId });
  }

  function syncRooms(next: number[]) {
    for (const id of joinedIds.value) if (!next.includes(id)) leave(id);
    for (const id of next) if (!joinedIds.value.includes(id)) join(id);
    joinedIds.value = [...next];
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
        joinedIds.value = [];
        syncRooms(normalizeIds(options.guildIds.value));
      });

      socket.on('guild-auction:changed', (event: GuildAuctionChangedEvent) => {
        const guildId = Number(event?.guildId);
        const lotId = Number(event?.lotId);
        if (!Number.isFinite(guildId) || guildId <= 0) return;
        if (!Number.isFinite(lotId) || lotId <= 0) return;
        if (!joinedIds.value.includes(guildId)) return;
        options.onChanged?.({ guildId, lotId });
      });
    });
  });

  onUnmounted(() => {
    for (const id of joinedIds.value) leave(id);
    joinedIds.value = [];
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(() => normalizeIds(options.guildIds.value), syncRooms, { deep: false });
}
