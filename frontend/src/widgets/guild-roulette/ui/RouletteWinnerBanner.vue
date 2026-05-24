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

const emit = defineEmits<{
  dismiss: [];
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
    <Transition name="roulette-wheel-winner">
      <div
        v-if="show"
        :key="displayKey"
        :class="transitionInnerClass"
      >
        <div
          class="pointer-events-auto relative flex max-w-full min-w-0 items-center gap-3 overflow-hidden rounded-lg border border-amber-300/70 bg-background/95 px-4 py-3 text-left shadow-[0_16px_36px_rgba(15,23,42,0.22)] ring-1 ring-white/70 backdrop-blur-md dark:border-amber-400/30 dark:bg-card/95 dark:ring-white/10"
          :class="isOverlay ? 'mx-1 w-[min(18rem,calc(100vw-2rem))]' : 'sm:text-left'"
          role="status"
          :title="winnerName ?? undefined"
        >
          <span
            class="absolute inset-x-0 top-0 h-0.5 bg-amber-400"
            aria-hidden="true"
          />
          <span
            class="absolute right-12 top-3 h-1.5 w-1.5 rounded-full bg-amber-300"
            aria-hidden="true"
          />
          <span
            class="absolute right-16 top-6 h-1 w-1 rounded-full bg-primary/50"
            aria-hidden="true"
          />
          <span
            class="absolute bottom-4 right-12 h-1 w-1 rounded-full bg-amber-400/70"
            aria-hidden="true"
          />

          <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-amber-50 text-amber-600 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20"
            aria-hidden="true"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="h-5 w-5"
              aria-hidden="true"
            >
              <path d="M8 21h8" />
              <path d="M12 17v4" />
              <path d="M7 4h10v4a5 5 0 0 1-10 0V4Z" />
              <path d="M17 4h2a2 2 0 0 1 2 2v1a4 4 0 0 1-4 4" />
              <path d="M7 4H5a2 2 0 0 0-2 2v1a4 4 0 0 0 4 4" />
            </svg>
          </span>

          <div class="min-w-0">
            <p class="text-xs font-medium uppercase leading-none tracking-normal text-muted-foreground">
              Победитель
            </p>
            <p class="mt-1 min-w-0 truncate text-lg font-semibold leading-tight text-foreground">
              {{ winnerName }}
            </p>
          </div>

          <button
            type="button"
            class="-mr-1 ml-auto inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="Скрыть оповещение победителя"
            @click="emit('dismiss')"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="h-4 w-4"
              aria-hidden="true"
            >
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.roulette-wheel-winner-enter-active {
  transition:
    opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.65s cubic-bezier(0.34, 1.45, 0.64, 1),
    filter 0.45s ease-out;
}
.roulette-wheel-winner-leave-active {
  transition: opacity 0.2s ease-out;
}
.roulette-wheel-winner-enter-from {
  opacity: 0;
  transform: scale(0.88) translateY(10px);
  filter: blur(3px);
}
.roulette-wheel-winner-enter-to {
  opacity: 1;
  transform: scale(1) translateY(0);
  filter: blur(0);
}
.roulette-wheel-winner-leave-to {
  opacity: 0;
}
</style>
