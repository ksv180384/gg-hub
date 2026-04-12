import type { SpinWheelServerParams } from '@/shared/lib/spinWheelTypes';
import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, watch, type Ref, type ShallowRef } from 'vue';
import { io, type Socket } from 'socket.io-client';

export type GuildAuctionWheelEntry =
  | { kind: 'guild'; character_id: number }
  | { kind: 'external'; id: string; name: string };

export type GuildAuctionSpinWheelExpose = {
  spinFromServer: (p: SpinWheelServerParams) => void;
};

function normalizeEntries(raw: unknown): GuildAuctionWheelEntry[] {
  if (!Array.isArray(raw)) return [];
  const out: GuildAuctionWheelEntry[] = [];
  for (const e of raw) {
    if (!e || typeof e !== 'object') continue;
    const o = e as Record<string, unknown>;
    if (o.kind === 'guild' && Number.isFinite(Number(o.character_id))) {
      out.push({ kind: 'guild', character_id: Number(o.character_id) });
    } else if (
      o.kind === 'external' &&
      typeof o.id === 'string' &&
      typeof o.name === 'string' &&
      o.name.trim()
    ) {
      out.push({ kind: 'external', id: o.id, name: o.name });
    }
  }
  return out;
}

/**
 * Синхронизация рулетки аукциона через Socket.IO.
 *
 * - Без `VITE_SOCKET_URL` (или пусто): подключение к **тому же origin**, что и сайт (`/socket.io`).
 *   Нужны прокси nginx и/или Vite dev (см. `default.conf`, `vite.config.ts`).
 * - Явный `VITE_SOCKET_URL`: только если сокет на другом хосте/порту (должен быть доступен **из браузера**).
 * - `VITE_SOCKET_URL=off` — не подключаться.
 *
 * `canManageAuctionWheel`: если false — только просмотр и приём синхронизации, без отправки изменений и spin-request.
 */
export function useGuildAuctionWheelSocket(options: {
  guildId: Ref<number>;
  wheelEntries: Ref<GuildAuctionWheelEntry[]>;
  spinWheelRef: ShallowRef<GuildAuctionSpinWheelExpose | null>;
  canManageAuctionWheel: Ref<boolean>;
}) {
  const socketRef = shallowRef<Socket | null>(null);
  const connected = ref(false);
  /** Получили начальное состояние с сервера — до этого не шлём локальные entries, чтобы не затереть комнату. */
  const hasServerState = ref(false);
  const applyingRemoteEntries = ref(false);
  const connectError = ref<string | null>(null);

  const rawEnv = (import.meta.env.VITE_SOCKET_URL as string | undefined)?.trim() ?? '';
  const syncOff = rawEnv === 'off' || rawEnv === 'false';
  /** Явный URL; пусто = тот же хост, что у страницы (через прокси /socket.io). */
  const explicitUrl = syncOff ? '' : rawEnv;
  const socketUrlForIo = explicitUrl.length > 0 ? explicitUrl : undefined;

  const configured = !syncOff;

  const remoteSpin = computed(
    () => configured && connected.value && hasServerState.value
  );

  function applyRemoteEntries(entries: unknown) {
    applyingRemoteEntries.value = true;
    options.wheelEntries.value = normalizeEntries(entries);
    nextTick(() => {
      applyingRemoteEntries.value = false;
      hasServerState.value = true;
    });
  }

  onMounted(() => {
    if (!configured || import.meta.env.SSR) return;

    const s = io(socketUrlForIo, {
      transports: ['websocket', 'polling'],
      path: '/socket.io',
      autoConnect: true,
    });
    socketRef.value = s;

    s.on('connect', () => {
      connected.value = true;
      connectError.value = null;
      hasServerState.value = false;
      const gid = options.guildId.value;
      if (gid > 0) {
        s.emit('auction:join', { guildId: gid });
      }
    });

    s.on('connect_error', (err: Error) => {
      connected.value = false;
      hasServerState.value = false;
      connectError.value = err?.message || 'Ошибка подключения';
    });

    s.on('disconnect', () => {
      connected.value = false;
      hasServerState.value = false;
    });

    s.on('auction:state', (msg: { entries?: unknown }) => {
      applyRemoteEntries(msg?.entries);
    });

    s.on('auction:entries', (msg: { entries?: unknown }) => {
      applyRemoteEntries(msg?.entries);
    });

    s.on('auction:spin', (payload: SpinWheelServerParams) => {
      options.spinWheelRef.value?.spinFromServer(payload);
    });
  });

  onUnmounted(() => {
    socketRef.value?.disconnect();
    socketRef.value = null;
  });

  watch(
    () => options.guildId.value,
    (id, prevId) => {
      const s = socketRef.value;
      if (!s?.connected) return;
      if (prevId && prevId > 0) {
        s.emit('auction:leave', { guildId: prevId });
      }
      if (id > 0) {
        hasServerState.value = false;
        s.emit('auction:join', { guildId: id });
      }
    }
  );

  watch(
    options.wheelEntries,
    (entries) => {
      if (!options.canManageAuctionWheel.value) return;
      const s = socketRef.value;
      if (!s?.connected || !hasServerState.value || applyingRemoteEntries.value) return;
      const gid = options.guildId.value;
      if (gid <= 0) return;
      s.emit('auction:entries:update', { guildId: gid, entries: [...entries] });
    },
    { deep: true }
  );

  function requestSpin(durationMs: number) {
    if (!options.canManageAuctionWheel.value) return;
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value) return;
    const gid = options.guildId.value;
    if (gid <= 0) return;
    const d = Number.isFinite(durationMs) && durationMs > 0 ? durationMs : 4000;
    s.emit('auction:spin-request', { guildId: gid, durationMs: d });
  }

  return {
    socketConfigured: configured,
    socketConnected: connected,
    socketConnectError: connectError,
    /** true, если задан явный VITE_SOCKET_URL (не same-origin). */
    socketUsesExplicitUrl: explicitUrl.length > 0,
    remoteSpin,
    requestSpin,
  };
}
