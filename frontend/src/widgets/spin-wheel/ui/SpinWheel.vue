<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Button } from '@/shared/ui';
import { useSpinWheel } from '../model/useSpinWheel';
import { drawWheel, getSoftColors } from '../lib/drawWheel';

const props = withDefaults(
  defineProps<{
    options: string[];
    duration?: number;
    /** Диаметр колеса в пикселях (по умолчанию 400) */
    size?: number;
  }>(),
  { size: 400 }
);

const canvas = ref<HTMLCanvasElement | null>(null);
const ctx = ref<CanvasRenderingContext2D | null>(null);
const segmentColors = ref<string[]>([]);

/** Tooltip при наведении на сегмент */
const tooltip = ref({ show: false, text: '', x: 0, y: 0 });

const { angle, result, spin } = useSpinWheel(
  () => props.options,
  props.duration ?? 4000
);

onMounted(() => {
  if (!canvas.value) return;
  ctx.value = canvas.value.getContext("2d");
  renderWheel();
});

const WHEEL_SIZE = props.size;
const CENTER = props.size / 2;
const POINTER_HEIGHT = 20;
const CANVAS_HEIGHT = WHEEL_SIZE + POINTER_HEIGHT;

/** Следим и за длиной, и за содержимым: при 1→1 (плейсхолдер → имя) length не меняется, но колесо нужно перерисовать. */
watch(
  () => [props.options?.length ?? 0, (props.options ?? []).join('\u0001')] as const,
  ([len]) => {
    segmentColors.value = getSoftColors(len);
    if (ctx.value) renderWheel();
  },
  { immediate: true }
);
watch(angle, renderWheel);

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
  const context = ctx.value;

  context.clearRect(0, 0, WHEEL_SIZE, CANVAS_HEIGHT);
  context.save();
  context.translate(0, POINTER_HEIGHT);
  context.translate(CENTER, CENTER);
  context.rotate((angle.value * Math.PI) / 180);
  context.translate(-CENTER, -CENTER);

  drawWheel(context, props.options, segmentColors.value, props.size || 400);

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
  <div class="relative inline-block">
    <canvas
      ref="canvas"
      :width="WHEEL_SIZE"
      :height="CANVAS_HEIGHT"
      class="cursor-pointer"
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

    <div class="mt-5 flex w-full justify-center">
      <Button
        size="lg"
        class="min-w-[11rem] text-base font-semibold shadow-lg shadow-primary/45 ring-2 ring-primary/55 ring-offset-2 ring-offset-background transition-[box-shadow,filter] hover:brightness-110 hover:shadow-xl hover:shadow-primary/50 hover:ring-primary/80"
        @click="spin"
      >
        Крутить
      </Button>
    </div>

    <div v-if="result">Выпало: {{ result }}</div>
  </div>
</template>

<style scoped>

</style>
