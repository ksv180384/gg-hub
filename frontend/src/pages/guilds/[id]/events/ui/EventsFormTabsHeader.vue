<script setup lang="ts">
import {
  EVENTS_FORM_TABS,
  type EventsFormTabId,
} from '../events-form-types';

defineProps<{
  activeTab: EventsFormTabId;
}>();

const emit = defineEmits<{
  (e: 'update:activeTab', value: EventsFormTabId): void;
}>();
</script>

<template>
  <div class="mb-4 flex flex-wrap gap-1 border-b" role="tablist">
    <button
      v-for="t in EVENTS_FORM_TABS"
      :key="t.id"
      type="button"
      role="tab"
      :aria-selected="activeTab === t.id"
      :aria-label="t.label"
      class="flex items-center gap-2 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium transition-colors sm:px-4"
      :class="
        activeTab === t.id
          ? 'border-primary text-primary'
          : 'border-transparent text-muted-foreground hover:text-foreground'
      "
      @click="emit('update:activeTab', t.id)"
    >
      <span class="flex shrink-0" aria-hidden="true">
        <svg
          v-if="t.id === 'information'"
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <circle cx="12" cy="12" r="10" />
          <path d="M12 16v-4" />
          <path d="M12 8h.01" />
        </svg>
        <svg
          v-else-if="t.id === 'participants'"
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          width="18"
          height="18"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
          <circle cx="9" cy="9" r="2" />
          <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
        </svg>
      </span>
      <span>{{ t.label }}</span>
    </button>
  </div>
</template>
