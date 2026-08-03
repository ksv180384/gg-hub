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
    const authUrl = process.env.ROULETTE_AUTH_URL
        ?? 'http://gg-nginx/api/v1/guild-roulette/socket-auth';
    const auditUrl = process.env.ROULETTE_AUDIT_URL
        ?? 'http://gg-nginx/api/v1/guild-roulette/audit';
    const internalToken = process.env.SOCKET_SERVER_INTERNAL_TOKEN ?? '';

    async function authenticateToken(token) {
        if (typeof token !== 'string' || token.length === 0) return null;

        const response = await fetch(authUrl, {
            method: 'POST',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify({ token }),
        });
        if (!response.ok) return null;

        const body = await response.json();
        const data = body?.data;
        const guildId = Number(data?.guild_id);
        const userId = Number(data?.user_id);
        if (!Number.isFinite(guildId) || guildId <= 0) return null;
        if (!Number.isFinite(userId) || userId <= 0) return null;

        return {
            guildId,
            userId,
            characterIds: Array.isArray(data?.character_ids)
                ? data.character_ids.map(Number).filter(id => Number.isFinite(id) && id > 0)
                : [],
            canManage: !!data?.can_manage,
        };
    }

    function authorizedGuildId(socket, payload, requireManage = false) {
        const session = socket.data?.guildRoulette;
        const requestedGuildId = Number(payload?.guildId);
        if (!session || session.guildId !== requestedGuildId) return null;
        if (requireManage && !session.canManage) return null;
        return session.guildId;
    }

    function audit(socket, action, metadata = {}) {
        const session = socket.data?.guildRoulette;
        if (!session || !internalToken) return;

        void fetch(auditUrl, {
            method: 'POST',
            headers: {
                'content-type': 'application/json',
                'x-socket-internal-token': internalToken,
            },
            body: JSON.stringify({
                guild_id: session.guildId,
                user_id: session.userId,
                action,
                metadata,
            }),
        }).then(response => {
            if (!response.ok && typeof log.warn === 'function') {
                log.warn(
                    { action, guildId: session.guildId, status: response.status },
                    'roulette audit request failed',
                );
            }
        }).catch(error => {
            if (typeof log.error === 'function') {
                log.error({ error, action, guildId: session.guildId }, 'roulette audit request failed');
            }
        });
    }
    io.on('connection', socket => {
        if (typeof log.info === 'function') {
            log.info({ id: socket.id }, 'socket client connected');
        }

        socket.on('roulette:join', async payload => {
            try {
                const session = await authenticateToken(payload?.token);
                if (!session) {
                    socket.emit('roulette:auth-error');
                    return;
                }

                const previousSession = socket.data?.guildRoulette;
                if (previousSession?.guildId) {
                    socket.leave(rouletteRoom(previousSession.guildId));
                }

                socket.data.guildRoulette = session;
                socket.join(rouletteRoom(session.guildId));
                const st = getOrCreateGuildState(session.guildId);
                socket.emit('roulette:state', {
                    entries: st.entries,
                    enrollmentOpen: !!st.enrollmentOpen,
                    eliminationMode: !!st.eliminationMode,
                    useDkpCoefficients: !!st.useDkpCoefficients,
                    dkpCoefficientOverrides: st.dkpCoefficientOverrides,
                    externalDkpCoefficientOverrides: st.externalDkpCoefficientOverrides,
                });
            } catch (error) {
                if (typeof log.error === 'function') {
                    log.error({ error, id: socket.id }, 'roulette authentication failed');
                }
                socket.emit('roulette:auth-error');
            }
        });

        socket.on('roulette:leave', () => {
            const session = socket.data?.guildRoulette;
            if (!session) return;
            socket.leave(rouletteRoom(session.guildId));
            delete socket.data.guildRoulette;
        });

        socket.on('roulette:entries:update', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const entries = sanitizeEntries(payload?.entries);
            if (JSON.stringify(st.entries) === JSON.stringify(entries)) return;
            st.entries = entries;
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
            audit(socket, 'roulette.entries_updated', { entries_count: st.entries.length });
        });

        socket.on('roulette:entries:add', payload => {
            const guildId = authorizedGuildId(socket, payload);
            if (!guildId) return;
            const session = socket.data.guildRoulette;
            const st = getOrCreateGuildState(guildId);
            if (!st.enrollmentOpen) return;
            const [candidate] = sanitizeEntries([payload?.entry]);
            if (!candidate) return;
            if (
                !session.canManage
                && (
                    candidate.kind !== 'guild'
                    || !session.characterIds.includes(candidate.character_id)
                )
            ) {
                return;
            }
            if (st.entries.length >= MAX_ENTRIES) return;
            const exists = st.entries.some(entry => {
                if (entry.kind !== candidate.kind) return false;
                if (candidate.kind === 'guild') {
                    return entry.character_id === candidate.character_id;
                }
                return entry.id === candidate.id;
            });
            if (exists) return;
            st.entries = [...st.entries, candidate];
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
            audit(socket, 'roulette.entry_added', {
                entry: candidate,
                entries_count: st.entries.length,
            });
        });

        socket.on('roulette:entries:remove', payload => {
            const guildId = authorizedGuildId(socket, payload);
            if (!guildId) return;
            const session = socket.data.guildRoulette;
            const st = getOrCreateGuildState(guildId);
            if (!st.enrollmentOpen) return;
            const [target] = sanitizeEntries([payload?.entry]);
            if (!target) return;
            if (
                !session.canManage
                && (
                    target.kind !== 'guild'
                    || !session.characterIds.includes(target.character_id)
                )
            ) {
                return;
            }
            const before = st.entries.length;
            st.entries = st.entries.filter(entry => {
                if (entry.kind !== target.kind) return true;
                if (target.kind === 'guild') {
                    return entry.character_id !== target.character_id;
                }
                return entry.id !== target.id;
            });
            if (st.entries.length === before) return;
            io.to(rouletteRoom(guildId)).emit('roulette:entries', { entries: st.entries });
            audit(socket, 'roulette.entry_removed', {
                entry: target,
                entries_count: st.entries.length,
            });
        });

        socket.on('roulette:enrollment:set', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const open = !!payload?.open;
            if (st.enrollmentOpen === open) return;
            st.enrollmentOpen = open;
            io.to(rouletteRoom(guildId)).emit('roulette:enrollment', { open: st.enrollmentOpen });
            audit(
                socket,
                open ? 'roulette.enrollment_opened' : 'roulette.enrollment_closed',
            );
        });

        socket.on('roulette:elimination-mode:set', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const enabled = !!payload?.enabled;
            if (st.eliminationMode === enabled) return;
            st.eliminationMode = enabled;
            io.to(rouletteRoom(guildId)).emit('roulette:elimination-mode', {
                enabled: st.eliminationMode,
            });
            audit(
                socket,
                enabled
                    ? 'roulette.elimination_mode_enabled'
                    : 'roulette.elimination_mode_disabled',
            );
        });

        socket.on('roulette:use-dkp-coefficients:set', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const enabled = !!payload?.enabled;
            if (st.useDkpCoefficients === enabled) return;
            st.useDkpCoefficients = enabled;
            io.to(rouletteRoom(guildId)).emit('roulette:use-dkp-coefficients', {
                enabled: st.useDkpCoefficients,
            });
            audit(
                socket,
                enabled
                    ? 'roulette.dkp_coefficients_enabled'
                    : 'roulette.dkp_coefficients_disabled',
            );
        });

        socket.on('roulette:dkp-coefficients:set', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const overrides = sanitizeDkpCoefficientOverrides(payload?.overrides);
            if (
                JSON.stringify(st.dkpCoefficientOverrides)
                === JSON.stringify(overrides)
            ) {
                return;
            }
            st.dkpCoefficientOverrides = overrides;
            io.to(rouletteRoom(guildId)).emit('roulette:dkp-coefficients', {
                overrides: st.dkpCoefficientOverrides,
            });
            audit(socket, 'roulette.dkp_coefficients_updated', {
                overrides_count: Object.keys(overrides).length,
            });
        });

        socket.on('roulette:external-dkp-coefficients:set', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const overrides = sanitizeExternalDkpCoefficientOverrides(payload?.overrides);
            if (
                JSON.stringify(st.externalDkpCoefficientOverrides)
                === JSON.stringify(overrides)
            ) {
                return;
            }
            st.externalDkpCoefficientOverrides = overrides;
            io.to(rouletteRoom(guildId)).emit('roulette:external-dkp-coefficients', {
                overrides: st.externalDkpCoefficientOverrides,
            });
            audit(socket, 'roulette.external_dkp_coefficients_updated', {
                overrides_count: Object.keys(overrides).length,
            });
        });

        socket.on('roulette:spin-request', payload => {
            const guildId = authorizedGuildId(socket, payload, true);
            if (!guildId) return;
            const st = getOrCreateGuildState(guildId);
            const n = st.entries.length;
            if (n < 1) return;

            const now = Date.now();
            if (st.spinLockedUntil > now) return;

            const durationMs = clampSpinDurationMs(payload?.durationMs);
            const spinPayload = buildSpinPayload(n, durationMs, payload?.weights);
            st.spinLockedUntil = now + spinPayload.duration + 400;

            if (st.enrollmentOpen) {
                st.enrollmentOpen = false;
                io.to(rouletteRoom(guildId)).emit('roulette:enrollment', { open: false });
            }

            io.to(rouletteRoom(guildId)).emit('roulette:spin', spinPayload);
            audit(socket, 'roulette.spin_started', {
                entries_count: n,
                winner: st.entries[spinPayload.winIdx] ?? null,
                duration_ms: spinPayload.duration,
            });
        });

        socket.on('disconnect', () => {
            if (typeof log.info === 'function') {
                log.info({ id: socket.id }, 'socket client disconnected');
            }
        });
    });
}
