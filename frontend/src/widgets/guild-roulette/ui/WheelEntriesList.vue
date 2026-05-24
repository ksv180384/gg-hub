<script setup lang="ts">
import { Button } from '@/shared/ui';
import type {
  UserColorTheme,
  WheelEntry,
} from '@/features/guild-roulette';

const props = defineProps<{
  entries: WheelEntry[];
  options: string[];
  showCoefficients?: boolean;
  weights?: number[];
  canEditCoefficients?: boolean;
  canEditWheelEntries: boolean;
  coefficientDraft?: (entry: WheelEntry) => string;
  coefficientError?: (entry: WheelEntry) => string;
  setCoefficientDraft?: (entry: WheelEntry, value: string) => void;
  applyCoefficient?: (entry: WheelEntry) => void;
  resetCoefficient?: (entry: WheelEntry) => void;
  resolveCanRemove?: (entry: WheelEntry) => boolean;
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

function formatCoefficient(index: number): string {
  const raw = props.weights?.[index];
  const value = Number(raw);
  const coefficient = Number.isFinite(value) && value >= 0 ? value : 1;
  return coefficient.toLocaleString('ru-RU', {
    maximumFractionDigits: 2,
    minimumFractionDigits: Number.isInteger(coefficient) ? 0 : 1,
  });
}

function blurEventTarget(ev: Event) {
  (ev.target as HTMLInputElement | null)?.blur();
}

function getCoefficientDraft(entry: WheelEntry, index: number): string {
  return props.coefficientDraft?.(entry) ?? formatCoefficient(index);
}

function getCoefficientError(entry: WheelEntry): string {
  return props.coefficientError?.(entry) ?? '';
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
        class="rounded border px-2 py-1 text-xs transition-colors"
        :style="entryStyle(entry)"
        :class="!resolveUserColor?.(entry) ? 'border-transparent' : 'border'"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="flex min-w-0 items-center gap-2">
            <span
              :class="
                entry.kind === 'external' && !resolveUserColor?.(entry)
                  ? 'truncate rounded-sm bg-amber-500/10 px-1 text-amber-900 dark:bg-amber-400/[0.12] dark:text-amber-200'
                  : 'truncate'
              "
            >
              {{ options[idx] }}
            </span>
            <span
              v-if="showCoefficients && canEditCoefficients"
              class="inline-flex h-6 shrink-0 items-center rounded-full border border-primary/25 bg-primary/10 pl-1.5 pr-0.5 text-primary shadow-sm transition-colors focus-within:border-primary/45 focus-within:bg-background focus-within:ring-2 focus-within:ring-primary/15"
            >
              <input
                type="number"
                min="0"
                max="999"
                step="0.1"
                inputmode="decimal"
                class="h-5 w-9 border-0 bg-transparent px-0.5 text-center text-[11px] font-semibold leading-none text-primary outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                :value="getCoefficientDraft(entry, idx)"
                @input="
                  setCoefficientDraft?.(
                    entry,
                    ($event.target as HTMLInputElement | null)?.value ?? ''
                  )
                "
                @blur="applyCoefficient?.(entry)"
                @keydown.enter.prevent="blurEventTarget"
              />
              <button
                type="button"
                class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[13px] font-semibold leading-none text-primary/70 transition-colors hover:bg-primary/15 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                title="Сбросить коэффициент"
                aria-label="Сбросить коэффициент"
                @click="resetCoefficient?.(entry)"
              >
                ×
              </button>
            </span>
            <span
              v-else-if="showCoefficients"
              class="shrink-0 rounded-full border border-primary/25 bg-primary/10 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-primary"
              :title="`Коэффициент: ${formatCoefficient(idx)}`"
            >
              x{{ formatCoefficient(idx) }}
            </span>
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
            x
          </Button>
        </div>
        <p
          v-if="showCoefficients && getCoefficientError(entry)"
          class="mt-1 text-xs text-destructive"
        >
          {{ getCoefficientError(entry) }}
        </p>
      </li>
    </ul>
  </div>
</template>
