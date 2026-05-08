<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  show: boolean;
  /** Меняется при каждом новом победителе — для повторного запуска transition. */
  displayKey: number;
  winnerName: string | null;
  /**
   * Если задано (px от верха блока с колесом = верх canvas) — бейдж центрируется
   * на диске. Иначе — строка в шапке справа (старое поведение).
   */
  wheelCenterTopPx?: number;
}>();

const isOverlay = computed(() => props.wheelCenterTopPx != null);

const rootClass = computed(() =>
  isOverlay.value
    ? 'pointer-events-none absolute left-1/2 z-10 flex w-[min(22rem,calc(100vw-2rem))] max-w-full justify-center'
    : 'relative flex min-h-[2.5rem] w-full min-w-0 items-center justify-center sm:flex-1 sm:justify-end'
);

const rootStyle = computed(() =>
  isOverlay.value
    ? {
        top: `${props.wheelCenterTopPx}px`,
        transform: 'translate(-50%, -50%)',
      }
    : undefined
);

const transitionInnerClass = computed(() =>
  isOverlay.value
    ? 'flex min-w-0 max-w-full justify-center'
    : 'flex w-full min-w-0 justify-center sm:w-auto sm:max-w-full sm:justify-end'
);
</script>

<template>
  <div :class="rootClass" :style="rootStyle" aria-live="polite">
    <Transition name="auction-wheel-winner">
      <div
        v-if="show"
        :key="displayKey"
        :class="transitionInnerClass"
      >
        <div
          class="pointer-events-auto relative flex max-w-full min-w-0 items-center gap-3 overflow-hidden rounded-2xl border-2 border-amber-400/80 bg-gradient-to-b from-amber-50 to-amber-100 px-4 py-3 shadow-2xl shadow-amber-500/25 ring-1 ring-amber-200/80 [filter:drop-shadow(0_10px_18px_rgba(0,0,0,0.22))] dark:border-amber-500/40 dark:from-amber-200/10 dark:to-amber-500/10 dark:shadow-amber-500/15 dark:ring-amber-400/20 dark:[filter:drop-shadow(0_10px_18px_rgba(0,0,0,0.35))]"
          :class="isOverlay ? 'mx-1' : 'sm:text-left'"
          role="status"
          :title="winnerName ?? undefined"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="h-7 w-7 shrink-0 text-amber-500 drop-shadow-sm"
            aria-hidden="true"
          >
            <path d="M8 21h8" />
            <path d="M12 17v4" />
            <path d="M7 4h10v4a5 5 0 0 1-10 0V4Z" />
            <path d="M17 4h2a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4h0" />
            <path d="M7 4H5a2 2 0 0 0-2 2v1a4 4 0 0 0 4 4h0" />
          </svg>

          <div class="min-w-0">
            <p class="text-sm font-extrabold leading-tight text-amber-950 dark:text-amber-100">
              Победитель!
            </p>
            <p class="min-w-0 truncate text-base font-semibold leading-tight text-amber-950/90 dark:text-amber-100/90">
              {{ winnerName }}
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.auction-wheel-winner-enter-active {
  transition:
    opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.65s cubic-bezier(0.34, 1.45, 0.64, 1),
    filter 0.45s ease-out;
}
.auction-wheel-winner-leave-active {
  transition: opacity 0.2s ease-out;
}
.auction-wheel-winner-enter-from {
  opacity: 0;
  transform: scale(0.88) translateY(10px);
  filter: blur(3px);
}
.auction-wheel-winner-enter-to {
  opacity: 1;
  transform: scale(1) translateY(0);
  filter: blur(0);
}
.auction-wheel-winner-leave-to {
  opacity: 0;
}
</style>
