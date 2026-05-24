/**
 * Комнаты рулетки гильдии: синхронизация участников и одного общего вращения.
 *
 * Поведение вращения на клиенте и согласование `norm` / `fullTurns` / длительности —
 * см. `frontend/src/widgets/spin-wheel/docs/SPIN_WHEEL_ROTATION.md`.
 */

const DEFAULT_SPIN_DURATION_MS = 4000;
const MIN_SPIN_DURATION_MS = 2000;
const MAX_SPIN_DURATION_MS = 60000;
const MAX_ENTRIES = 400;

/**
 * @typedef {Object} GuildRouletteState
 * @property {unknown[]} entries
 * @property {number} spinLockedUntil
 * @property {boolean} enrollmentOpen — открыт ли набор «Участвовать» для рядовых членов гильдии
 */
/** @type {Map<string, GuildRouletteState>} */
const guildRouletteState = new Map();

function rouletteRoom(guildId) {
    return `guild:${guildId}:roulette`;
}

function getOrCreateGuildState(guildId) {
    const key = String(guildId);
    if (!guildRouletteState.has(key)) {
        guildRouletteState.set(key, {
            entries: [],
            spinLockedUntil: 0,
            enrollmentOpen: false,
            eliminationMode: false,
            useDkpCoefficients: false,
            dkpCoefficientOverrides: {},
            externalDkpCoefficientOverrides: {},
        });
    }
    return guildRouletteState.get(key);
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

function sanitizeDkpCoefficientOverrides(raw) {
    if (!raw || typeof raw !== 'object') return {};
    const out = {};
    for (const [key, value] of Object.entries(raw)) {
        const characterId = Number(key);
        const coefficient = Number(value);
        if (
            Number.isFinite(characterId) &&
            characterId > 0 &&
            Number.isFinite(coefficient) &&
            coefficient >= 0 &&
            coefficient <= 999
        ) {
            out[String(Math.round(characterId))] = coefficient;
        }
    }
    return out;
}

function sanitizeExternalDkpCoefficientOverrides(raw) {
    if (!raw || typeof raw !== 'object') return {};
    const out = {};
    for (const [key, value] of Object.entries(raw)) {
        const coefficient = Number(value);
        if (
            typeof key === 'string' &&
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
function sanitizeSpinWeights(raw, n) {
    if (!Array.isArray(raw) || raw.length !== n) {
        return Array.from({ length: n }, () => 1);
    }
    const weights = raw.map(weight => {
        const value = Number(weight);
        return Number.isFinite(value) && value >= 0 ? value : 1;
    });
    return weights.reduce((sum, weight) => sum + weight, 0) > 0
        ? weights
        : Array.from({ length: n }, () => 1);
}

function getWeightedSegmentBounds(index, weights) {
    const total = weights.reduce((sum, weight) => sum + weight, 0);
    if (total <= 0) return { start: 0, arc: 360 };
    const startWeight = weights
        .slice(0, index)
        .reduce((sum, weight) => sum + weight, 0);
    return {
        start: (360 * startWeight) / total,
        arc: (360 * (weights[index] ?? 1)) / total,
    };
}

function getRandomWeightedIndex(weights) {
    const total = weights.reduce((sum, weight) => sum + weight, 0);
    let pick = Math.random() * total;
    for (let i = 0; i < weights.length; i++) {
        pick -= weights[i] ?? 0;
        if (pick <= 0) return i;
    }
    return Math.max(0, weights.length - 1);
}

function buildSpinPayload(n, durationMs, rawWeights) {
    const weights = sanitizeSpinWeights(rawWeights, n);
    const winIdx = getRandomWeightedIndex(weights);
    const { start, arc } = getWeightedSegmentBounds(winIdx, weights);
    const margin = Math.min(arc * 0.06, 8);
    const span = Math.max(arc - 2 * margin, arc * 0.5);
    const norm = start + margin + Math.random() * span;
    const fullTurns = fullTurnsFromDurationMs(durationMs);
    return {
        winIdx,
        norm,
        fullTurns,
        duration: durationMs,
        weights,
    };
}

function clampSpinDurationMs(raw) {
    const n = Number(raw);
    if (!Number.isFinite(n) || n <= 0) return DEFAULT_SPIN_DURATION_MS;
    return Math.min(MAX_SPIN_DURATION_MS, Math.max(MIN_SPIN_DURATION_MS, Math.round(n)));
}

export function registerRouletteSocketHandlers(io, log = console) {
    io.on('connection', socket => {
        if (typeof log.info === 'function') {
            log.info({ id: socket.id }, 'socket client connected');
        }

        socket.on('roulette:join', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const room = rouletteRoom(guildId);
            socket.join(room);
            const st = getOrCreateGuildState(guildId);
            socket.emit('roulette:state', {
                entries: st.entries,
                enrollmentOpen: !!st.enrollmentOpen,
                eliminationMode: !!st.eliminationMode,
                useDkpCoefficients: !!st.useDkpCoefficients,
                dkpCoefficientOverrides: st.dkpCoefficientOverrides,
                externalDkpCoefficientOverrides: st.externalDkpCoefficientOverrides,
            });
        });

        socket.on('roulette:leave', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            socket.leave(rouletteRoom(guildId));
        });

        socket.on('roulette:entries:update', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            st.entries = sanitizeEntries(payload?.entries);
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
        });

        /**
         * Добавление одной записи (используется рядовым участником гильдии при «Участвовать»).
         * Доступно только когда `enrollmentOpen = true`, и только если такой записи ещё нет.
         */
        socket.on('roulette:entries:add', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            if (!st.enrollmentOpen) return;
            const [candidate] = sanitizeEntries([payload?.entry]);
            if (!candidate) return;
            if (st.entries.length >= MAX_ENTRIES) return;
            const exists = st.entries.some(e => {
                if (e.kind !== candidate.kind) return false;
                if (candidate.kind === 'guild') {
                    return e.character_id === candidate.character_id;
                }
                return e.id === candidate.id;
            });
            if (exists) return;
            st.entries = [...st.entries, candidate];
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
        });

        /**
         * Удаление одной записи (рядовой участник убирает только свою — клиентское ограничение).
         * Доступно только когда `enrollmentOpen = true`.
         */
        socket.on('roulette:entries:remove', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            if (!st.enrollmentOpen) return;
            const [target] = sanitizeEntries([payload?.entry]);
            if (!target) return;
            const before = st.entries.length;
            st.entries = st.entries.filter(e => {
                if (e.kind !== target.kind) return true;
                if (target.kind === 'guild') {
                    return e.character_id !== target.character_id;
                }
                return e.id !== target.id;
            });
            if (st.entries.length === before) return;
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
        });

        /**
         * Открыть/закрыть набор участников. Доступ проверяется на клиенте по праву
         * `upravlenie-ruletkoi`; сокет-сервер без auth — этот хендлер просто синхронизирует.
         */
        socket.on('roulette:enrollment:set', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            const open = !!payload?.open;
            if (st.enrollmentOpen === open) return;
            st.enrollmentOpen = open;
            io.to(rouletteRoom(guildId)).emit('roulette:enrollment', { open: st.enrollmentOpen });
        });

        socket.on('roulette:elimination-mode:set', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            const enabled = !!payload?.enabled;
            if (st.eliminationMode === enabled) return;
            st.eliminationMode = enabled;
            io.to(rouletteRoom(guildId)).emit('roulette:elimination-mode', {
                enabled: st.eliminationMode,
            });
        });

        socket.on('roulette:use-dkp-coefficients:set', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            const enabled = !!payload?.enabled;
            if (st.useDkpCoefficients === enabled) return;
            st.useDkpCoefficients = enabled;
            io.to(rouletteRoom(guildId)).emit('roulette:use-dkp-coefficients', {
                enabled: st.useDkpCoefficients,
            });
        });

        socket.on('roulette:dkp-coefficients:set', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            st.dkpCoefficientOverrides = sanitizeDkpCoefficientOverrides(payload?.overrides);
            io.to(rouletteRoom(guildId)).emit('roulette:dkp-coefficients', {
                overrides: st.dkpCoefficientOverrides,
            });
        });

        socket.on('roulette:external-dkp-coefficients:set', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            st.externalDkpCoefficientOverrides = sanitizeExternalDkpCoefficientOverrides(payload?.overrides);
            io.to(rouletteRoom(guildId)).emit('roulette:external-dkp-coefficients', {
                overrides: st.externalDkpCoefficientOverrides,
            });
        });

        socket.on('roulette:spin-request', payload => {
            const guildId = Number(payload?.guildId);
            if (!Number.isFinite(guildId) || guildId <= 0) return;
            const st = getOrCreateGuildState(guildId);
            const n = st.entries.length;
            if (n < 1) return;

            const now = Date.now();
            if (st.spinLockedUntil > now) return;

            const durationMs = clampSpinDurationMs(payload?.durationMs);
            const spinPayload = buildSpinPayload(n, durationMs, payload?.weights);
            st.spinLockedUntil = now + spinPayload.duration + 400;

            // Запуск розыгрыша автоматически закрывает набор участников.
            if (st.enrollmentOpen) {
                st.enrollmentOpen = false;
                io.to(rouletteRoom(guildId)).emit('roulette:enrollment', { open: false });
            }

            io.to(rouletteRoom(guildId)).emit('roulette:spin', spinPayload);
        });

        socket.on('disconnect', () => {
            if (typeof log.info === 'function') {
                log.info({ id: socket.id }, 'socket client disconnected');
            }
        });
    });
}
