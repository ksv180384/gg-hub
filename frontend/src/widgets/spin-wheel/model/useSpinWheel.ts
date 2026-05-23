import type { SpinWheelServerParams } from '@/shared/lib/spinWheelTypes';
import { onUnmounted, ref } from 'vue';

export type { SpinWheelServerParams };

function mod360(deg: number): number {
  return ((deg % 360) + 360) % 360;
}

function sanitizeWeights(length: number, raw?: number[]): number[] {
  if (!raw || raw.length !== length) {
    return Array.from({ length }, () => 1);
  }
  const weights = raw.map((weight) => {
    const n = Number(weight);
    return Number.isFinite(n) && n >= 0 ? n : 1;
  });
  return getTotalWeight(weights) > 0 ? weights : Array.from({ length }, () => 1);
}

function getTotalWeight(weights: number[]): number {
  return weights.reduce((sum, weight) => sum + weight, 0);
}

function getWeightedSegmentBounds(index: number, weights: number[]) {
  const total = getTotalWeight(weights);
  if (total <= 0) return { start: 0, arc: 360 };
  const startWeight = weights
    .slice(0, index)
    .reduce((sum, weight) => sum + weight, 0);
  return {
    start: (360 * startWeight) / total,
    arc: (360 * (weights[index] ?? 1)) / total,
  };
}

function getWeightedIndexAtNorm(norm: number, weights: number[]): number {
  const total = getTotalWeight(weights);
  if (total <= 0) return 0;
  let start = 0;
  const normalized = mod360(norm);
  for (let i = 0; i < weights.length; i++) {
    const arc = (360 * (weights[i] ?? 0)) / total;
    const end = start + arc;
    if (normalized >= start && normalized < end) return i;
    start = end;
  }
  return Math.max(0, weights.length - 1);
}

function getRandomWeightedSegment(weights: number[]) {
  const total = getTotalWeight(weights);
  let pick = Math.random() * total;
  let winIdx = 0;
  for (let i = 0; i < weights.length; i++) {
    pick -= weights[i] ?? 0;
    if (pick <= 0) {
      winIdx = i;
      break;
    }
  }
  const { start, arc } = getWeightedSegmentBounds(winIdx, weights);
  const margin = Math.min(arc * 0.06, 8);
  const span = Math.max(arc - 2 * margin, arc * 0.5);
  return {
    winIdx,
    norm: start + margin + Math.random() * span,
  };
}

const SPIN_FULL_TURNS_MAX = 120;
const SPIN_FULL_TURNS_REF_MS = 4_000;
const SPIN_FULL_TURNS_PER_REF = 8;
const SPIN_FULL_TURNS_MIN = 5;

function fullTurnsForDurationMs(durationMs: number): number {
  const n = Math.round((SPIN_FULL_TURNS_PER_REF * durationMs) / SPIN_FULL_TURNS_REF_MS);
  return Math.max(SPIN_FULL_TURNS_MIN, Math.min(SPIN_FULL_TURNS_MAX, n));
}

const SPIN_ACCEL_TIME_END = 0.2;
const SPIN_CRUISE_TIME_END = 0.7;
const SPIN_EASE_AFTER_ACCEL = 0.1;
const SPIN_CRUISE_ANGLE_PORTION = 0.75;

const SPIN_CRUISE_SLOPE =
  SPIN_CRUISE_ANGLE_PORTION / (SPIN_CRUISE_TIME_END - SPIN_ACCEL_TIME_END);

function spinTailDecelBlend(v: number): number {
  const x = Math.min(1, Math.max(0, v));
  return 1 - Math.pow(1 - x, 3);
}

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

export function useSpinWheel(
  optionsGetter: () => string[],
  durationGetter: () => number = () => 4000,
  weightsGetter: () => number[] | undefined = () => undefined
) {
  const angle = ref(0);
  const result = ref<string | null>(null);
  const resultIndex = ref<number | null>(null);
  const resultSeq = ref(0);
  const isSpinning = ref(false);
  const spinCountdownSeconds = ref<number | null>(null);

  let rafId = 0;

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

      spinCountdownSeconds.value = Math.max(
        0,
        Math.ceil((animDuration - elapsed) / 1000)
      );

      if (progress < 1) {
        rafId = requestAnimationFrame(animate);
        return;
      }

      rafId = 0;
      angle.value = finalAngle;
      spinCountdownSeconds.value = null;
      onDone();
      isSpinning.value = false;
    };

    rafId = requestAnimationFrame(animate);
  }

  const spin = () => {
    const options = optionsGetter();
    if (options.length === 0 || isSpinning.value) return;

    const weights = sanitizeWeights(options.length, weightsGetter());
    const { norm } = getRandomWeightedSegment(weights);
    const remainderTarget = mod360(270 - norm);

    const startAngle = angle.value;
    const startRem = mod360(startAngle);
    const delta = mod360(remainderTarget - startRem);
    const duration = durationGetter();
    const fullTurns = fullTurnsForDurationMs(duration);
    const finalAngle = startAngle + delta + 360 * fullTurns;

    runSpinAnimation(startAngle, finalAngle, duration, calculateResult);
  };

  const spinFromServer = (params: SpinWheelServerParams) => {
    const options = optionsGetter();
    if (options.length === 0 || isSpinning.value) return;

    let norm = params.norm;
    if (!Number.isFinite(norm)) return;
    norm = mod360(norm);

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

  const calculateResult = () => {
    const options = optionsGetter();
    if (options.length === 0) {
      resultIndex.value = null;
      result.value = null;
      resultSeq.value += 1;
      return;
    }
    const topAngle = 270;
    const normalizedAngle = mod360(topAngle - mod360(angle.value));
    const weights = sanitizeWeights(options.length, weightsGetter());
    const selectedIndex = getWeightedIndexAtNorm(normalizedAngle, weights);
    resultIndex.value = selectedIndex;
    result.value = options[selectedIndex] ?? null;
    resultSeq.value += 1;
  };

  return {
    angle,
    result,
    resultIndex,
    resultSeq,
    isSpinning,
    spinCountdownSeconds,
    spin,
    spinFromServer,
  };
}
