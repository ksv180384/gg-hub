<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
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

const { angle, result, spin } = useSpinWheel(
  props.options,
  props.duration ?? 4000
);

onMounted(() => {
  if (!canvas.value) return;
  ctx.value = canvas.value.getContext("2d");
  renderWheel();
});

const WHEEL_SIZE = props.size;
const CENTER = props.size / 2;
const POINTER_HEIGHT = 20;//Math.round(props.size * 0.11);
const CANVAS_HEIGHT = WHEEL_SIZE + POINTER_HEIGHT;

watch(
  () => props.options?.length ?? 0,
  (len) => {
    segmentColors.value = getSoftColors(len);
    if (ctx.value) renderWheel();
  },
  { immediate: true }
);
watch(angle, renderWheel);

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
  <div>
    <canvas ref="canvas" :width="WHEEL_SIZE" :height="CANVAS_HEIGHT"></canvas>

    <button @click="spin">Крутить</button>

    <div v-if="result">Выпало: {{ result }}</div>
  </div>
</template>

<style scoped>

</style>
