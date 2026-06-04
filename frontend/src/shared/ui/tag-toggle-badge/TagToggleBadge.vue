<script setup lang="ts">
import Badge from '@/shared/ui/badge/Badge.vue';

interface Props {
  label: string;
  selected?: boolean;
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  selected: false,
  disabled: false,
});

const emit = defineEmits<{
  toggle: [];
}>();
</script>

<template>
  <button
    type="button"
    class="inline-flex rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50"
    :aria-pressed="props.selected"
    :disabled="props.disabled"
    @click="emit('toggle')"
  >
    <Badge
      :variant="props.selected ? 'default' : 'secondary'"
      :class="props.selected ? 'gap-1.5 border-primary bg-primary text-primary-foreground shadow-sm' : ''"
    >
      <svg
        v-if="props.selected"
        xmlns="http://www.w3.org/2000/svg"
        width="12"
        height="12"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M20 6 9 17l-5-5" />
      </svg>
      {{ props.label }}
    </Badge>
  </button>
</template>
