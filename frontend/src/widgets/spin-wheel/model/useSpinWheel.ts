/**
 * Логика вращения рулетки: финальный угол, нормированная кривая ease по времени, RAF.
 *
 * Подробное описание геометрии (norm, стрелка 270°, delta, fullTurns), кривой ease (разгон / круиз / торможение)
 * и синхронизации с сокетом — в
 * `frontend/src/widgets/spin-wheel/docs/SPIN_WHEEL_ROTATION.md`.
 */
import type { SpinWheelServerParams } from '@/shared/lib/spinWheelTypes';
import { onUnmounted, ref } from 'vue';

export type { SpinWheelServerParams };

/** Остаток деления на 360 в диапазоне [0, 360). */
function mod360(deg: number): number {
    return ((deg % 360) + 360) % 360;
}

/** Верхняя граница полных оборотов (сервер и приём `spinFromServer`). 60 с → 120 об. */
const SPIN_FULL_TURNS_MAX = 120;
/** Эталон: при этой длительности целевое число оборотов = SPIN_FULL_TURNS_PER_REF (ω ≈ const по T). */
const SPIN_FULL_TURNS_REF_MS = 4_000;
const SPIN_FULL_TURNS_PER_REF = 8;
const SPIN_FULL_TURNS_MIN = 5;

/**
 * Полные обороты пропорциональны T: N ≈ 8×(T/4с) → 360×N/T ≈ const (та же «живость», что у 4 с).
 * Должно совпадать с `fullTurnsFromDurationMs` в `socket_server/auctionSocketHandler.js`.
 */
function fullTurnsForDurationMs(durationMs: number): number {
    const n = Math.round((SPIN_FULL_TURNS_PER_REF * durationMs) / SPIN_FULL_TURNS_REF_MS);
    return Math.max(SPIN_FULL_TURNS_MIN, Math.min(SPIN_FULL_TURNS_MAX, n));
}

/** Конец плавного разгона (0–10% и 10–20% объединены в один кубический участок 0–20%). */
const SPIN_ACCEL_TIME_END = 0.2;
/** Конец ровного круиза на максимальной ω (20–70% времени). */
const SPIN_CRUISE_TIME_END = 0.7;
/** Доля угла после разгона, до круиза и торможения. */
const SPIN_EASE_AFTER_ACCEL = 0.1;
/** Доля угла на ровном круизе (максимальная скорость). */
const SPIN_CRUISE_ANGLE_PORTION = 0.75;

const SPIN_CRUISE_SLOPE =
    SPIN_CRUISE_ANGLE_PORTION / (SPIN_CRUISE_TIME_END - SPIN_ACCEL_TIME_END);

/**
 * Финальные 30% времени: монотонный ease-out 1−(1−v)³ — φ′(0)=3, φ′(1)=0, без перегиба выше 1.
 * Раньше использовали квинтику с φ′(0)=3 и φ″(1)=0; она давала φ(v)>1 при v≈0.9, затем спад к 1 —
 * ease убывал → колесо визуально крутилось назад в конце длинного спина.
 */
function spinTailDecelBlend(v: number): number {
    const x = Math.min(1, Math.max(0, v));
    return 1 - Math.pow(1 - x, 3);
}

/**
 * Нормированный прогресс ease ∈ [0, 1]:
 * 0–20% — плавный разгон (u³, ω=0 в t=0);
 * 20–70% — постоянная максимальная ω;
 * 70–100% — плавное торможение.
 */
function spinEasedProgressForTimeline(elapsedMs: number, animDurationMs: number): number {
    const T = animDurationMs;
    if (T <= 0) return 1;
    const elapsed = Math.min(Math.max(0, elapsedMs), T);
    const t = elapsed / T;

    if (t <= SPIN_ACCEL_TIME_END) {
        const u = t / SPIN_ACCEL_TIME_END;
        return SPIN_EASE_AFTER_ACCEL * u * u * u;
    }

    if (t <= SPIN_CRUISE_TIME_END) {
        return SPIN_EASE_AFTER_ACCEL + SPIN_CRUISE_SLOPE * (t - SPIN_ACCEL_TIME_END);
    }

    const easeBeforeTail = SPIN_EASE_AFTER_ACCEL + SPIN_CRUISE_ANGLE_PORTION;
    const tailAngle = 1 - easeBeforeTail;
    const v = (t - SPIN_CRUISE_TIME_END) / (1 - SPIN_CRUISE_TIME_END);
    return easeBeforeTail + tailAngle * spinTailDecelBlend(v);
}

/**
 * @param optionsGetter — актуальный список подписей сегментов (для результата и проверок).
 * @param durationGetter — длительность локального спина (мс), если не передана с сервера.
 */
export function useSpinWheel(
    optionsGetter: () => string[],
    durationGetter: () => number = () => 4000
) {
    const angle = ref(0);
    const result = ref<string | null>(null);
    const isSpinning = ref(false);
    /** Обратный отсчёт секунд во время вращения; null — колесо не крутится. */
    const spinCountdownSeconds = ref<number | null>(null);

    let rafId = 0;

    /** Интерполяция angle от startAngle к finalAngle за animDuration мс; по завершении — onDone. */
    function runSpinAnimation(
        startAngle: number,
        finalAngle: number,
        animDuration: number,
        onDone: () => void
    ) {
        if (rafId) cancelAnimationFrame(rafId);
        isSpinning.value = true;
        spinCountdownSeconds.value = Math.max(0, Math.ceil(animDuration / 1000));
        const start = performance.now();

        const animate = (now: number) => {
            const elapsed = now - start;
            const progress = Math.min(elapsed / animDuration, 1);
            const ease = spinEasedProgressForTimeline(elapsed, animDuration);
            angle.value = startAngle + ease * (finalAngle - startAngle);

            spinCountdownSeconds.value = Math.max(0, Math.ceil((animDuration - elapsed) / 1000));

            if (progress < 1) {
                rafId = requestAnimationFrame(animate);
            } else {
                rafId = 0;
                angle.value = finalAngle;
                spinCountdownSeconds.value = null;
                onDone();
                isSpinning.value = false;
            }
        };

        rafId = requestAnimationFrame(animate);
    }

    /** Локальный розыгрыш: случайный norm/fullTurns, без сокета. */
    const spin = () => {
        const options = optionsGetter();
        if (options.length === 0 || isSpinning.value) return;

        const n = options.length;
        const arc = 360 / n;
        // Сначала индекс победителя, затем случайная точка внутри его дуги — иначе остановка визуально «прилипает» к одному углу по mod 360.
        const winIdx = Math.floor(Math.random() * n);
        const margin = Math.min(arc * 0.06, 8);
        const span = Math.max(arc - 2 * margin, arc * 0.5);
        const norm = winIdx * arc + margin + Math.random() * span;
        // Стрелка смотрит на 270° в системе canvas; такой остаток angle даёт под ней norm.
        const remainderTarget = mod360(270 - norm);

        const startAngle = angle.value;
        const startRem = mod360(startAngle);
        const delta = mod360(remainderTarget - startRem);
        const fullTurns = fullTurnsForDurationMs(durationGetter());
        const finalAngle = startAngle + delta + 360 * fullTurns;

        runSpinAnimation(startAngle, finalAngle, durationGetter(), calculateResult);
    };

    /**
     * Общий спин для всех: norm, fullTurns и duration должны совпадать с payload сокета
     * (сервер генерирует один раз на комнату).
     */
    const spinFromServer = (params: SpinWheelServerParams) => {
        const options = optionsGetter();
        if (options.length === 0 || isSpinning.value) return;

        const n = options.length;
        if (n <= 0) return;

        const arc = 360 / n;
        const margin = Math.min(arc * 0.06, 8);
        const span = Math.max(arc - 2 * margin, arc * 0.5);
        const maxNorm = (n - 1) * arc + margin + span;
        let norm = params.norm;
        if (!Number.isFinite(norm)) return;
        norm = Math.min(Math.max(norm, margin), maxNorm);

        const remainderTarget = mod360(270 - norm);
        const startAngle = angle.value;
        const startRem = mod360(startAngle);
        const delta = mod360(remainderTarget - startRem);
        const fullTurns = Math.max(0, Math.min(SPIN_FULL_TURNS_MAX, Math.floor(params.fullTurns)));
        const finalAngle = startAngle + delta + 360 * fullTurns;

        const animDuration =
            Number.isFinite(params.duration) && params.duration > 0
                ? params.duration
                : durationGetter();

        runSpinAnimation(startAngle, finalAngle, animDuration, calculateResult);
    };

    onUnmounted(() => {
        if (rafId) cancelAnimationFrame(rafId);
        rafId = 0;
        isSpinning.value = false;
        spinCountdownSeconds.value = null;
    });

    /** После остановки: какой сегмент оказался под стрелкой (topAngle = 270°). */
    const calculateResult = () => {
        const options = optionsGetter();
        if (options.length === 0) {
            result.value = null;
            return;
        }
        // Совпадает с поворотом колеса в SpinWheel.vue (верх = 12 часов).
        const topAngle = 270;
        const normalizedAngle = mod360(topAngle - mod360(angle.value));
        const arc = 360 / options.length;
        const selectedIndex = Math.floor(normalizedAngle / arc) % options.length;
        result.value = options[selectedIndex];
    };

    return {
        angle,
        result,
        isSpinning,
        spinCountdownSeconds,
        spin,
        spinFromServer,
    };
}
