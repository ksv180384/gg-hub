import type { SpinWheelServerParams } from '@/shared/lib/spinWheelTypes';
import {
  computed,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  shallowRef,
  watch,
  type Ref,
  type ShallowRef,
} from 'vue';
import type { Socket } from 'socket.io-client';

export type GuildRouletteWheelEntry =
  | { kind: 'guild'; character_id: number }
  | { kind: 'external'; id: string; name: string };

export type GuildRouletteSpinWheelExpose = {
  spinFromServer: (p: SpinWheelServerParams) => void;
  spin?: () => void;
  animateRemoveSegment?: (index: number) => Promise<void>;
  spinCountdownSeconds: Ref<number | null>;
  isSpinning: Ref<boolean>;
};

function normalizeEntries(raw: unknown): GuildRouletteWheelEntry[] {
  if (!Array.isArray(raw)) return [];
  const out: GuildRouletteWheelEntry[] = [];
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

function normalizeDkpCoefficientOverrides(raw: unknown): Record<number, number> {
  if (!raw || typeof raw !== 'object') return {};
  const out: Record<number, number> = {};
  for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
    const characterId = Number(key);
    const coefficient = Number(value);
    if (
      Number.isFinite(characterId) &&
      characterId > 0 &&
      Number.isFinite(coefficient) &&
      coefficient >= 0 &&
      coefficient <= 999
    ) {
      out[characterId] = coefficient;
    }
  }
  return out;
}

function normalizeExternalDkpCoefficientOverrides(raw: unknown): Record<string, number> {
  if (!raw || typeof raw !== 'object') return {};
  const out: Record<string, number> = {};
  for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
    const coefficient = Number(value);
    if (
      key.length > 0 &&
      key.length <= 120 &&
      Number.isFinite(coefficient) &&
      coefficient >= 0 &&
      coefficient <= 999
    ) {
      out[key] = coefficient;
    }
  }
  return out;
}

/**
 * Синхронизация рулетки через Socket.IO.
 *
 * - Без `VITE_SOCKET_URL` (или пусто): подключение к **тому же origin**, что и сайт (`/socket.io`).
 *   Нужны прокси nginx и/или Vite dev (см. `default.conf`, `vite.config.ts`).
 * - Явный `VITE_SOCKET_URL`: только если сокет на другом хосте/порту (должен быть доступен **из браузера**).
 * - `VITE_SOCKET_URL=off` — не подключаться.
 *
 * `canManageRouletteWheel`: если false — только просмотр и приём синхронизации, без отправки изменений и spin-request.
 */
export function useGuildRouletteWheelSocket(options: {
  guildId: Ref<number>;
  wheelEntries: Ref<GuildRouletteWheelEntry[]>;
  eliminationMode: Ref<boolean>;
  useDkpCoefficients: Ref<boolean>;
  dkpCoefficientOverrides: Ref<Record<number, number>>;
  externalDkpCoefficientOverrides: Ref<Record<string, number>>;
  spinWheelRef: ShallowRef<GuildRouletteSpinWheelExpose | null>;
  canManageRouletteWheel: Ref<boolean>;
}) {
  const socketRef = shallowRef<Socket | null>(null);
  const connected = ref(false);
  /** Получили начальное состояние с сервера — до этого не шлём локальные entries, чтобы не затереть комнату. */
  const hasServerState = ref(false);
  const applyingRemoteEntries = ref(false);
  const applyingRemoteEliminationMode = ref(false);
  const applyingRemoteUseDkpCoefficients = ref(false);
  const applyingRemoteDkpCoefficientOverrides = ref(false);
  const applyingRemoteExternalDkpCoefficientOverrides = ref(false);
  const connectError = ref<string | null>(null);
  /** Открыт ли набор участников (синхронизируется со всеми в комнате). */
  const enrollmentOpen = ref(false);

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

  function applyRemoteEnrollment(open: unknown) {
    enrollmentOpen.value = !!open;
  }

  function applyRemoteEliminationMode(enabled: unknown) {
    applyingRemoteEliminationMode.value = true;
    options.eliminationMode.value = !!enabled;
    nextTick(() => {
      applyingRemoteEliminationMode.value = false;
    });
  }

  function applyRemoteUseDkpCoefficients(enabled: unknown) {
    applyingRemoteUseDkpCoefficients.value = true;
    options.useDkpCoefficients.value = !!enabled;
    nextTick(() => {
      applyingRemoteUseDkpCoefficients.value = false;
    });
  }

  function applyRemoteDkpCoefficientOverrides(overrides: unknown) {
    applyingRemoteDkpCoefficientOverrides.value = true;
    options.dkpCoefficientOverrides.value =
      normalizeDkpCoefficientOverrides(overrides);
    nextTick(() => {
      applyingRemoteDkpCoefficientOverrides.value = false;
    });
  }

  function applyRemoteExternalDkpCoefficientOverrides(overrides: unknown) {
    applyingRemoteExternalDkpCoefficientOverrides.value = true;
    options.externalDkpCoefficientOverrides.value =
      normalizeExternalDkpCoefficientOverrides(overrides);
    nextTick(() => {
      applyingRemoteExternalDkpCoefficientOverrides.value = false;
    });
  }

  async function loadIo() {
    const mod = await import('socket.io-client');
    return mod.io;
  }

  onMounted(() => {
    if (!configured || import.meta.env.SSR) return;

    void loadIo().then((io) => {
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
          s.emit('roulette:join', { guildId: gid });
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

      s.on(
        'roulette:state',
        (msg: {
          entries?: unknown;
          enrollmentOpen?: unknown;
            eliminationMode?: unknown;
            useDkpCoefficients?: unknown;
            dkpCoefficientOverrides?: unknown;
            externalDkpCoefficientOverrides?: unknown;
          }) => {
          applyRemoteEntries(msg?.entries);
          applyRemoteEnrollment(msg?.enrollmentOpen);
          applyRemoteEliminationMode(msg?.eliminationMode);
            applyRemoteUseDkpCoefficients(msg?.useDkpCoefficients);
            applyRemoteDkpCoefficientOverrides(msg?.dkpCoefficientOverrides);
            applyRemoteExternalDkpCoefficientOverrides(
              msg?.externalDkpCoefficientOverrides
            );
          }
        );

      s.on('roulette:entries', (msg: { entries?: unknown }) => {
        applyRemoteEntries(msg?.entries);
      });

      s.on('roulette:enrollment', (msg: { open?: unknown }) => {
        applyRemoteEnrollment(msg?.open);
      });

      s.on('roulette:elimination-mode', (msg: { enabled?: unknown }) => {
        applyRemoteEliminationMode(msg?.enabled);
      });

      s.on('roulette:use-dkp-coefficients', (msg: { enabled?: unknown }) => {
        applyRemoteUseDkpCoefficients(msg?.enabled);
      });

      s.on('roulette:dkp-coefficients', (msg: { overrides?: unknown }) => {
        applyRemoteDkpCoefficientOverrides(msg?.overrides);
      });

      s.on('roulette:external-dkp-coefficients', (msg: { overrides?: unknown }) => {
        applyRemoteExternalDkpCoefficientOverrides(msg?.overrides);
      });

      s.on('roulette:spin', (payload: SpinWheelServerParams) => {
        // Сервер автоматически закрывает набор при запуске; продублируем локально
        // на случай рассогласования порядка событий.
        enrollmentOpen.value = false;
        options.spinWheelRef.value?.spinFromServer(payload);
      });
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
        s.emit('roulette:leave', { guildId: prevId });
      }
      if (id > 0) {
        hasServerState.value = false;
        s.emit('roulette:join', { guildId: id });
      }
    }
  );

  watch(
    options.wheelEntries,
    (entries) => {
      if (!options.canManageRouletteWheel.value) return;
      const s = socketRef.value;
      if (!s?.connected || !hasServerState.value || applyingRemoteEntries.value) return;
      const gid = options.guildId.value;
      if (gid <= 0) return;
      s.emit('roulette:entries:update', { guildId: gid, entries: [...entries] });
    },
    { deep: true }
  );

  watch(options.eliminationMode, (enabled) => {
    if (!options.canManageRouletteWheel.value) return;
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value || applyingRemoteEliminationMode.value) {
      return;
    }
    const gid = options.guildId.value;
    if (gid <= 0) return;
    s.emit('roulette:elimination-mode:set', { guildId: gid, enabled: !!enabled });
  });

  watch(options.useDkpCoefficients, (enabled) => {
    if (!options.canManageRouletteWheel.value) return;
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value || applyingRemoteUseDkpCoefficients.value) {
      return;
    }
    const gid = options.guildId.value;
    if (gid <= 0) return;
    s.emit('roulette:use-dkp-coefficients:set', { guildId: gid, enabled: !!enabled });
  });

  watch(
    options.dkpCoefficientOverrides,
    (overrides) => {
      if (!options.canManageRouletteWheel.value) return;
      const s = socketRef.value;
      if (
        !s?.connected ||
        !hasServerState.value ||
        applyingRemoteDkpCoefficientOverrides.value
      ) {
        return;
      }
      const gid = options.guildId.value;
      if (gid <= 0) return;
      s.emit('roulette:dkp-coefficients:set', {
        guildId: gid,
        overrides: { ...overrides },
      });
    },
    { deep: true }
  );

  watch(
    options.externalDkpCoefficientOverrides,
    (overrides) => {
      if (!options.canManageRouletteWheel.value) return;
      const s = socketRef.value;
      if (
        !s?.connected ||
        !hasServerState.value ||
        applyingRemoteExternalDkpCoefficientOverrides.value
      ) {
        return;
      }
      const gid = options.guildId.value;
      if (gid <= 0) return;
      s.emit('roulette:external-dkp-coefficients:set', {
        guildId: gid,
        overrides: { ...overrides },
      });
    },
    { deep: true }
  );

  function sanitizeSpinWeights(weights?: number[]): number[] | undefined {
    if (!Array.isArray(weights)) return undefined;
    const sanitized = weights.map((weight) => {
      const n = Number(weight);
      return Number.isFinite(n) && n >= 0 ? n : 1;
    });
    return sanitized.reduce((sum, weight) => sum + weight, 0) > 0
      ? sanitized
      : weights.map(() => 1);
  }

  function requestSpin(durationMs: number, weights?: number[]) {
    if (!options.canManageRouletteWheel.value) return;
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value) return;
    const gid = options.guildId.value;
    if (gid <= 0) return;
    const d = Number.isFinite(durationMs) && durationMs > 0 ? durationMs : 4000;
    s.emit('roulette:spin-request', {
      guildId: gid,
      durationMs: d,
      weights: sanitizeSpinWeights(weights),
    });
  }

  /**
   * Открыть/закрыть набор участников. Доступно только пользователям с правом
   * `upravlenie-ruletkoi` (флаг проверяется здесь же, чтобы не плодить ошибки на сервере).
   */
  function setEnrollmentOpen(open: boolean) {
    if (!options.canManageRouletteWheel.value) return;
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value) return;
    const gid = options.guildId.value;
    if (gid <= 0) return;
    s.emit('roulette:enrollment:set', { guildId: gid, open: !!open });
  }

  /**
   * Добавить одну запись через сервер (для рядовых членов гильдии: одиночные операции
   * вместо отправки всего массива записей). Сервер примет только при открытом наборе.
   */
  function addEntryViaServer(entry: GuildRouletteWheelEntry): boolean {
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value) return false;
    const gid = options.guildId.value;
    if (gid <= 0) return false;
    s.emit('roulette:entries:add', { guildId: gid, entry });
    return true;
  }

  /** Удалить одну запись через сервер (рядовой участник убирает свою же). */
  function removeEntryViaServer(entry: GuildRouletteWheelEntry): boolean {
    const s = socketRef.value;
    if (!s?.connected || !hasServerState.value) return false;
    const gid = options.guildId.value;
    if (gid <= 0) return false;
    s.emit('roulette:entries:remove', { guildId: gid, entry });
    return true;
  }

  return {
    socketConfigured: configured,
    socketConnected: connected,
    socketConnectError: connectError,
    /** true, если задан явный VITE_SOCKET_URL (не same-origin). */
    socketUsesExplicitUrl: explicitUrl.length > 0,
    remoteSpin,
    requestSpin,
    enrollmentOpen,
    setEnrollmentOpen,
    addEntryViaServer,
    removeEntryViaServer,
  };
}
