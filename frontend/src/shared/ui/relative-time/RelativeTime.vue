<script setup lang="ts">
import { computed } from 'vue';
import { formatRelativeTime, formatDateTimeFull } from '@/shared/lib/relativeTime';
import { cn } from '@/shared/lib/utils';

interface Props {
  /** ISO строка даты/времени */
  date: string | undefined | null;
  /** Часовой пояс пользователя (из профиля); если не передан, используется локальный. */
  timezone?: string;
  /** Тег корневого элемента */
  tag?: 'span' | 'time';
  class?: string;
}

const props = withDefaults(defineProps<Props>(), {
  tag: 'span',
});

const displayText = computed(() => formatRelativeTime(props.date, props.timezone));
const tooltipText = computed(() => formatDateTimeFull(props.date, props.timezone));
</script>

<template>
  <component
    :is="tag"
    :class="cn('inline', props.class)"
    :title="tooltipText || undefined"
    :datetime="date ?? undefined"
    class="cursor-default"
  >
    {{ displayText }}
  </component>
</template>
