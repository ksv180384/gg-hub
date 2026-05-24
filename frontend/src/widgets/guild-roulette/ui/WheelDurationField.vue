<script setup lang="ts">
import { Input, Label } from '@/shared/ui';
import {
  WHEEL_SPIN_SEC_MAX,
  WHEEL_SPIN_SEC_MIN,
} from '@/features/guild-roulette';

defineProps<{
  modelValue: string;
  countdownSeconds: number | null;
  /** Когда true — поле длительности нельзя менять (например, во время спина). */
  disabled?: boolean;
}>();

defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'blur'): void;
}>();
</script>

<template>
  <div class="flex flex-col items-center gap-2 border-b border-border pb-4 sm:flex-row sm:flex-wrap sm:justify-center">
    <div class="flex items-center gap-2">
      <Label
        for="wheel-spin-seconds"
        class="shrink-0 whitespace-nowrap text-sm text-muted-foreground"
      >
        Время вращения, с
      </Label>
      <Input
        id="wheel-spin-seconds"
        :model-value="modelValue"
        type="number"
        :min="WHEEL_SPIN_SEC_MIN"
        :max="WHEEL_SPIN_SEC_MAX"
        step="0.5"
        :disabled="disabled"
        class="w-[5rem] shrink-0"
        @update:model-value="$emit('update:modelValue', String($event ?? ''))"
        @blur="$emit('blur')"
      />
    </div>
    <div
      class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 sm:justify-center"
    >
      <p
        v-if="countdownSeconds !== null"
        class="max-w-md text-center text-sm font-semibold tabular-nums text-foreground sm:text-left"
        aria-live="polite"
        role="status"
      >
        Осталось {{ countdownSeconds }}&nbsp;с
      </p>
      <p
        v-else
        class="max-w-md text-center text-xs text-muted-foreground sm:text-left"
      >
        Допустимо {{ WHEEL_SPIN_SEC_MIN }}–{{ WHEEL_SPIN_SEC_MAX }} с.
      </p>
      <div v-if="$slots.right" class="flex shrink-0 items-center justify-center">
        <slot name="right" />
      </div>
    </div>
  </div>
</template>
