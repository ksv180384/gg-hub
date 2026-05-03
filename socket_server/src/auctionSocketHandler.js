/**
 * Комнаты рулетки аукциона гильдии: синхронизация участников и одного общего вращения.
 *
 * Поведение вращения на клиенте и согласование `norm` / `fullTurns` / длительности —
 * см. `frontend/src/widgets/spin-wheel/docs/SPIN_WHEEL_ROTATION.md`.
 */

const DEFAULT_SPIN_DURATION_MS = 4000;
const MIN_SPIN_DURATION_MS = 2000;
const MAX_SPIN_DURATION_MS = 60000;
const MAX_ENTRIES = 400;

/** @type {Map<string, { entries: unknown[], spinLockedUntil: number }>} */
const guildAuctionState = new Map();

function auctionRoom(guildId) {
    return `guild:${guildId}:auction`;
}

function getOrCreateGuildState(guildId) {
    const key = String(guildId);
    if (!guildAuctionState.has(key)) {
        guildAuctionState.set(key, { entries: [], spinLockedUntil: 0 });
    }
    return guildAuctionState.get(key);
}

function sanitizeEntries(raw) {
    if (!Array.isArray(raw)) return [];
    const out = [];
    for (const e of raw.slice(0, MAX_ENTRIES)) {
        if (!e || typeof e !== 'object') continue;
        if (e.kind === 'guild' && Number.isFinite(Number(e.character_id))) {
            out.push({ kind: 'guild', character_id: Number(e.character_id) });
        } else if (
            e.kind === 'external' &&
            typeof e.id === 'string' &&
            e.id.length > 0 &&
            e.id.length <= 120 &&
            typeof e.name === 'string'
        ) {
            const name = e.name.trim().slice(0, 200);
            if (name) out.push({ kind: 'external', id: e.id.slice(0, 120), name });
        }
    }
    return out;
}

const MIN_FULL_TURNS = 5;
const MAX_FULL_TURNS = 120;
const FULL_TURNS_REF_MS = 4_000;
const FULL_TURNS_PER_REF = 8;

/**
 * N = round(8 × T / 4с), в клампе — типичная ω на круизе ~ не ниже, чем у 4 с при 8 оборотах.
 * Синхронно с `fullTurnsForDurationMs` во фронте `useSpinWheel.ts`.
 */
function fullTurnsFromDurationMs(durationMs) {
    const n = Math.round((FULL_TURNS_PER_REF * durationMs) / FULL_TURNS_REF_MS);
    return Math.max(MIN_FULL_TURNS, Math.min(MAX_FULL_TURNS, n));
}

/**
 * Та же геометрия, что во фронте `useSpinWheel`.
 * @param {number} n — число сегментов (участников)
 * @param {number} durationMs
 */
function buildSpinPayload(n, durationMs) {
    const arc = 360 / n;
    const margin = Math.min(arc * 0.06, 8);
    const span = Math.max(arc - 2 * margin, arc * 0.5);
    const winIdx = Math.floor(Math.random() * n);
    const norm = winIdx * arc + margin + Math.random() * span;
    const fullTurns = fullTurnsFromDurationMs(durationMs);
    return {
        winIdx,
        norm,
        fullTurns,
        duration: durationMs,
    };
}

function clampSpinDurationMs(raw) {
    const n = Number(raw);
    if (!Number.isFinite(n) || n <= 0) return DEFAULT_SPIN_DURATION_MS;
    return Math.min(MAX_SPIN_DURATION_MS, Math.max(MIN_SPIN_DURATION_MS, Math.round(n)));
}

export function registerAuctionSocketHandlers(io, log = console) {
    io.on('connection', socket => {
        if (typeof log.info === 'function') {
            log.info({ id: socket.id }, 'socket client connected');
        }

        socket.on('auction:join', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const room = auctionRoom(guildId);
            socket.join(room);
            const st = getOrCreateGuildState(guildId);
            socket.emit('auction:state', { entries: st.entries });
        });

        socket.on('auction:leave', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            socket.leave(auctionRoom(guildId));
        });

        socket.on('auction:entries:update', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            st.entries = sanitizeEntries(payload?.entries);
            io.to(auctionRoom(guildId)).emit('auction:entries', { entries: st.entries });
        });

        socket.on('auction:spin-request', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            const n = st.entries.length;
            if (n < 1) return;

            const now = Date.now();
            if (st.spinLockedUntil > now) return;

            const durationMs = clampSpinDurationMs(payload?.durationMs);
            const spinPayload = buildSpinPayload(n, durationMs);
            st.spinLockedUntil = now + spinPayload.duration + 400;
            io.to(auctionRoom(guildId)).emit('auction:spin', spinPayload);
        });

        socket.on('disconnect', () => {
            if (typeof log.info === 'function') {
                log.info({ id: socket.id }, 'socket client disconnected');
            }
        });
    });
}
