<script setup lang="ts">
import { computed, ref } from 'vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  class?: string;
  type?: string;
  modelValue?: string;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
});

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>();

const model = computed({
  get: () => props.modelValue ?? '',
  /** `type="number"` даёт в v-model число; наружу — всегда string, как в Props. */
  set: (v: string | number) =>
    emit('update:modelValue', v === null || v === undefined ? '' : String(v)),
});

const root = ref<HTMLInputElement | null>(null);
const showPassword = ref(false);

const isPasswordType = computed(() => props.type === 'password');
const resolvedType = computed(() =>
  isPasswordType.value && showPassword.value ? 'text' : props.type,
);
const passwordToggleLabel = computed(() =>
  showPassword.value ? 'Скрыть пароль' : 'Показать пароль',
);

const inputClass = computed(() =>
  cn(
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
    isPasswordType.value && 'pr-10',
    props.class,
  ),
);

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value;
}

defineExpose({
  focus: (options?: FocusOptions) => root.value?.focus(options),
});
</script>

<template>
  <div v-if="isPasswordType" class="relative">
    <input
      ref="root"
      v-model="model"
      :type="resolvedType"
      :class="inputClass"
      v-bind="$attrs"
    >
    <button
      type="button"
      class="absolute inset-y-0 right-0 flex w-9 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      :aria-label="passwordToggleLabel"
      :aria-pressed="showPassword"
      @click="togglePasswordVisibility"
    >
      <svg
        v-if="showPassword"
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" />
        <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" />
        <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" />
        <path d="m2 2 20 20" />
      </svg>
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
        <circle cx="12" cy="12" r="3" />
      </svg>
    </button>
  </div>
  <input
    v-else
    ref="root"
    v-model="model"
    :type="resolvedType"
    :class="inputClass"
    v-bind="$attrs"
  >
</template>
