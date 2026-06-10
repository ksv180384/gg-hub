<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  deletedAt: string | null | undefined;
}>();

const formatter = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  timeZone: 'Europe/Moscow',
});

const title = computed(() => {
  if (!props.deletedAt) return 'Гильдия удалена';
  const date = new Date(props.deletedAt);
  if (Number.isNaN(date.getTime())) return 'Гильдия удалена';

  return `Гильдия удалена: ${formatter.format(date)}`;
});
</script>

<template>
  <span
    v-if="deletedAt"
    class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-muted-foreground"
    :title="title"
    aria-label="Гильдия удалена"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="10" />
      <line x1="12" x2="12" y1="8" y2="12" />
      <line x1="12" x2="12.01" y1="16" y2="16" />
    </svg>
  </span>
</template>
