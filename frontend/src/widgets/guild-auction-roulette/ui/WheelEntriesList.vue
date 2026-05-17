<script setup lang="ts">
import { Button } from '@/shared/ui';
import type {
  UserColorTheme,
  WheelEntry,
} from '@/features/guild-auction-roulette';

const props = defineProps<{
  entries: WheelEntry[];
  /** Подписи сегментов колеса (тех же индексов, что entries). */
  options: string[];
  /** Кнопки удаления для записей менеджера. */
  canEditWheelEntries: boolean;
  /**
   * Карточка-менеджер? Если да — показываем ✕ для всех; иначе — резолвер `canRemove`
   * из родителя укажет, чьи записи удалять.
   */
  resolveCanRemove?: (entry: WheelEntry) => boolean;
  /** Резолвер цвета пользователя по записи (для подсветки группы персонажей одного user). */
  resolveUserColor?: (entry: WheelEntry) => UserColorTheme | null;
}>();

defineEmits<{
  (e: 'remove-guild', characterId: number): void;
  (e: 'remove-external', id: string): void;
}>();

function entryStyle(entry: WheelEntry) {
  const color = props.resolveUserColor?.(entry) ?? null;
  if (!color) return undefined;
  return {
    backgroundColor: color.bg,
    borderColor: color.border,
    color: color.text,
  } as Record<string, string>;
}

function canRemoveEntry(entry: WheelEntry): boolean {
  if (props.canEditWheelEntries) return true;
  if (props.resolveCanRemove) return props.resolveCanRemove(entry);
  return false;
}
</script>

<template>
  <div v-if="entries.length > 0" class="space-y-2 text-sm">
    <p class="text-center font-medium sm:text-left">
      На колесе ({{ entries.length }})
    </p>
    <ul class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-border p-2">
      <li
        v-for="(entry, idx) in entries"
        :key="entry.kind === 'guild' ? `g-${entry.character_id}` : `x-${entry.id}`"
        class="flex items-center justify-between gap-2 rounded border px-2 py-1 text-xs transition-colors"
        :style="entryStyle(entry)"
        :class="
          !resolveUserColor?.(entry)
            ? 'border-transparent'
            : 'border'
        "
      >
        <span
          :class="
            entry.kind === 'external' && !resolveUserColor?.(entry)
              ? 'rounded-sm bg-amber-500/10 px-1 text-amber-900 dark:bg-amber-400/[0.12] dark:text-amber-200'
              : 'truncate'
          "
        >
          {{ options[idx] }}
        </span>
        <Button
          v-if="canRemoveEntry(entry)"
          type="button"
          variant="ghost"
          size="sm"
          class="h-7 shrink-0 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
          aria-label="Убрать с колеса"
          @click="
            entry.kind === 'guild'
              ? $emit('remove-guild', entry.character_id)
              : $emit('remove-external', entry.id)
          "
        >
          ✕
        </Button>
      </li>
    </ul>
  </div>
</template>
