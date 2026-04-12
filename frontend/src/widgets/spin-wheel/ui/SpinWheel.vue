<script setup lang="ts">
/**
 * Canvas-рулетка: `angle` из `useSpinWheel` крутит диск через `rotate()`; стрелка статична сверху.
 * Подробности траектории и синхронизации — `widgets/spin-wheel/docs/SPIN_WHEEL_ROTATION.md`.
 */
import { onMounted, ref, watch } from 'vue';
import { Button } from '@/shared/ui';
import type { SpinWheelServerParams } from '@/shared/lib/spinWheelTypes';
import { useSpinWheel } from '../model/useSpinWheel';
import { drawWheel, getSoftColors } from '../lib/drawWheel';

const props = withDefaults(
  defineProps<{
    options: string[];
    duration?: number;
    /** Диаметр колеса в пикселях (по умолчанию 400) */
    size?: number;
    /**
     * true — кнопка «Крутить» не крутит локально, только событие `spin-request` (ожидается сокет).
     * false — локальный random spin.
     */
    remoteSpin?: boolean;
    /** Внешнее отключение (например, нет участников на колесе). */
    spinDisabled?: boolean;
    /** false — не показывать кнопку «Крутить» (только просмотр / синхронный розыгрыш с сервера). */
    showSpinButton?: boolean;
    /** true — не показывать обратный отсчёт под колесом (его выводит родитель). */
    hideInlineCountdown?: boolean;
  }>(),
  { size: 400, remoteSpin: false, spinDisabled: false, showSpinButton: true, hideInlineCountdown: false }
);

const canvas = ref<HTMLCanvasElement | null>(null);
const ctx = ref<CanvasRenderingContext2D | null>(null);
const segmentColors = ref<string[]>([]);

/** Tooltip при наведении на сегмент */
const tooltip = ref({ show: false, text: '', x: 0, y: 0 });

const emit = defineEmits<{
  (e: 'result', value: string | null): void;
  /** Длительность вращения (мс) для сервера при синхронном режиме. */
  (e: 'spin-request', durationMs: number): void;
  /** Начало вращения (локально, с сервера или сразу после нажатия «Крутить» в remote-режиме). */
  (e: 'spin-start'): void;
}>();

const { angle, result, isSpinning, spinCountdownSeconds, spin, spinFromServer } = useSpinWheel(
  () => props.options,
  () => props.duration ?? 4000
);

defineExpose({
  spinFromServer: (p: SpinWheelServerParams) => spinFromServer(p),
  isSpinning,
  spinCountdownSeconds,
});

function onSpinClick() {
  if (props.spinDisabled || isSpinning.value) return;
  if (props.remoteSpin) {
    emit('spin-start');
    emit('spin-request', props.duration ?? 4000);
    return;
  }
  spin();
}

watch(isSpinning, (spinning) => {
  if (spinning) emit('spin-start');
});

watch(result, (v) => emit('result', v));

onMounted(() => {
  if (!canvas.value) return;
  ctx.value = canvas.value.getContext('2d', { alpha: true });
  renderWheel();
});

const WHEEL_SIZE = props.size;
const CENTER = props.size / 2;
const POINTER_HEIGHT = 20;
const CANVAS_HEIGHT = WHEEL_SIZE + POINTER_HEIGHT;

/** Растровый кэш сегментов и текста (без поворота); на каждом кадре анимации — только rotate + drawImage. */
let wheelCache: HTMLCanvasElement | null = null;
let wheelCacheCtx: CanvasRenderingContext2D | null = null;
let wheelCacheDirty = true;

function ensureWheelCacheCanvas() {
  if (wheelCache && wheelCache.width === WHEEL_SIZE) return;
  wheelCache = document.createElement('canvas');
  wheelCache.width = WHEEL_SIZE;
  wheelCache.height = WHEEL_SIZE;
  wheelCacheCtx = wheelCache.getContext('2d', { alpha: true });
}

function rebuildWheelCache() {
  ensureWheelCacheCanvas();
  if (!wheelCacheCtx) return;
  wheelCacheCtx.clearRect(0, 0, WHEEL_SIZE, WHEEL_SIZE);
  drawWheel(wheelCacheCtx, props.options, segmentColors.value, WHEEL_SIZE);
  wheelCacheDirty = false;
}

/** Следим и за длиной, и за содержимым: при 1→1 (плейсхолдер → имя) length не меняется, но колесо нужно перерисовать. */
watch(
  () => [props.options?.length ?? 0, (props.options ?? []).join('\u0001')] as const,
  ([len]) => {
    segmentColors.value = getSoftColors(len);
    wheelCacheDirty = true;
    if (ctx.value) renderWheel();
  },
  { immediate: true }
);
/** sync: отрисовка в том же тике, что и обновление angle из RAF, без отставания на один кадр. */
watch(angle, renderWheel, { flush: 'sync' });

/** Вычисляет индекс сегмента под курсором (или -1). */
function getSegmentAt(offsetX: number, offsetY: number): number {
  const dx = offsetX - CENTER;
  const dy = offsetY - POINTER_HEIGHT - CENTER;
  const dist = Math.sqrt(dx * dx + dy * dy);
  if (dist > CENTER || props.options.length === 0) return -1;

  const canvasAngle = Math.atan2(dy, dx);
  const modelAngle = ((canvasAngle - (angle.value * Math.PI) / 180) % (2 * Math.PI) + 2 * Math.PI) % (2 * Math.PI);
  const arc = (2 * Math.PI) / props.options.length;
  return Math.floor(modelAngle / arc) % props.options.length;
}

function onWheelMouseMove(e: MouseEvent) {
  const el = canvas.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const scaleX = el.width / rect.width;
  const scaleY = el.height / rect.height;
  const offsetX = (e.clientX - rect.left) * scaleX;
  const offsetY = (e.clientY - rect.top) * scaleY;

  const idx = getSegmentAt(offsetX, offsetY);
  if (idx >= 0) {
    tooltip.value = {
      show: true,
      text: props.options[idx],
      x: e.clientX,
      y: e.clientY,
    };
  } else {
    tooltip.value.show = false;
  }
}

function onWheelMouseLeave() {
  tooltip.value.show = false;
}

function renderWheel() {
  if (!ctx.value) return;
  if (wheelCacheDirty) {
    rebuildWheelCache();
  }
  if (!wheelCache) return;

  const context = ctx.value;

  context.clearRect(0, 0, WHEEL_SIZE, CANVAS_HEIGHT);
  context.save();
  context.translate(0, POINTER_HEIGHT);
  context.translate(CENTER, CENTER);
  // Положительный angle — по часовой; верх круга после поворота = 270° в логике результата.
  context.rotate((angle.value * Math.PI) / 180);
  context.drawImage(wheelCache, -CENTER, -CENTER);
  context.restore();

  // Стрелка за пределами круга (вверху), остриём вниз
  drawPointer(context);
}

function drawPointer(ctx: CanvasRenderingContext2D) {
  const cx = CENTER;
  const tipY = POINTER_HEIGHT;
  const baseY = 0;
  const w = Math.max(28, Math.round(props.size * 0.07));

  ctx.beginPath();
  ctx.moveTo(cx, tipY+10);
  ctx.lineTo(cx - w / 2, baseY);
  ctx.lineTo(cx + w / 2, baseY);
  ctx.closePath();
  ctx.fillStyle = '#c62828';
  ctx.fill();
  ctx.strokeStyle = '#1b0000';
  ctx.lineWidth = 2;
  ctx.stroke();
}
</script>

<template>
  <div class="relative inline-flex flex-col items-center gap-4">
    <canvas
      ref="canvas"
      :width="WHEEL_SIZE"
      :height="CANVAS_HEIGHT"
      class="cursor-pointer shrink-0"
      @mousemove="onWheelMouseMove"
      @mouseleave="onWheelMouseLeave"
    />

    <div
      v-if="tooltip.show && tooltip.text"
      class="pointer-events-none fixed z-50 max-w-xs rounded-md border border-border bg-popover px-3 py-2 text-sm text-popover-foreground shadow-md"
      :style="{ left: tooltip.x + 12 + 'px', top: tooltip.y + 12 + 'px' }"
    >
      {{ tooltip.text }}
    </div>

    <div
      v-if="!hideInlineCountdown && spinCountdownSeconds !== null"
      class="min-h-[1.75rem] text-center text-xl font-semibold tabular-nums tracking-tight text-foreground"
      aria-live="polite"
      role="status"
    >
      {{ spinCountdownSeconds }}&nbsp;с
    </div>

    <div v-if="showSpinButton" class="flex w-full justify-center">
      <Button
        size="lg"
        class="min-w-[11rem] text-base font-semibold shadow-lg shadow-primary/45 ring-2 ring-primary/55 ring-offset-2 ring-offset-background transition-[box-shadow,filter] hover:brightness-110 hover:shadow-xl hover:shadow-primary/50 hover:ring-primary/80"
        :disabled="spinDisabled || isSpinning"
        @click="onSpinClick"
      >
        Крутить
      </Button>
    </div>
  </div>
</template>
