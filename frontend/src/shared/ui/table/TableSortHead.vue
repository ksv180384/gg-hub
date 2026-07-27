<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/shared/lib/utils';

interface Props {
  active?: boolean;
  direction?: 'asc' | 'desc';
}

const props = withDefaults(defineProps<Props>(), {
  active: false,
  direction: 'asc',
});

const emit = defineEmits<{
  click: [];
}>();

const ariaSort = computed(() => {
  if (!props.active) return 'none';
  return props.direction === 'asc' ? 'ascending' : 'descending';
});
</script>

<template>
  <th
    :aria-sort="ariaSort"
    :class="
      cn(
        'h-11 px-2 text-left align-middle text-xs font-semibold uppercase tracking-wide text-muted-foreground',
        $attrs.class as string,
      )
    "
  >
    <button
      type="button"
      class="group inline-flex w-full items-center gap-1.5 px-2 py-3 text-left hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
      @click="emit('click')"
    >
      <span><slot /></span>
      <span
        class="text-sm leading-none"
        :class="props.active ? 'text-foreground' : 'opacity-40 group-hover:opacity-70'"
        aria-hidden="true"
      >
        {{ props.active ? (props.direction === 'asc' ? '↑' : '↓') : '↕' }}
      </span>
    </button>
  </th>
</template>
