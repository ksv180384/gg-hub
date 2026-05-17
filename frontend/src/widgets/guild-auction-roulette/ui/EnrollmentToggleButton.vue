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
    class="shrink-0"
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
