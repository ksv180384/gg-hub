<script setup lang="ts">
import { Button } from '@/shared/ui';

const props = defineProps<{
  enrollmentOpen: boolean;
  /** Если true, нельзя переключать (например, идёт вращение). */
  disabled?: boolean;
  /** Состояние сокета — для дизейбла, когда нет коннекта. */
  remoteAvailable: boolean;
}>();

defineEmits<{
  (e: 'open'): void;
  (e: 'close'): void;
}>();
</script>

<template>
  <Button
    v-if="!enrollmentOpen"
    type="button"
    size="sm"
    variant="default"
    :disabled="props.disabled || !remoteAvailable"
    class="shrink-0 cursor-pointer bg-emerald-600 text-white shadow-lg shadow-emerald-600/30 ring-2 ring-emerald-500/40 ring-offset-2 ring-offset-background transition-[filter] hover:bg-emerald-600 hover:brightness-110 focus-visible:ring-2 focus-visible:ring-emerald-400/60 dark:bg-emerald-500 dark:text-emerald-950 dark:shadow-emerald-500/20 dark:ring-emerald-400/30"
    @click="$emit('open')"
  >
    Открыть набор участников
  </Button>
  <Button
    v-else
    type="button"
    size="sm"
    variant="outline"
    :disabled="props.disabled || !remoteAvailable"
    class="shrink-0 cursor-pointer border-amber-600/60 text-amber-700 hover:bg-amber-100/60 dark:border-amber-500/60 dark:text-amber-200 dark:hover:bg-amber-500/10"
    @click="$emit('close')"
  >
    Закрыть набор участников
  </Button>
</template>
