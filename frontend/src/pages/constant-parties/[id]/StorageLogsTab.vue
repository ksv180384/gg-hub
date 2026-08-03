<script setup lang="ts">
import type { DateRange } from 'radix-vue';
import {
  ChevronLeft,
  ChevronRight,
  Gift,
  History,
  LogOut,
  PackagePlus,
  Pencil,
  RotateCcw,
  Trash2,
  UserMinus,
  UserPlus,
  Warehouse,
} from '@lucide/vue';
import {
  Badge,
  Button,
  DateRangePicker,
  Select,
  Spinner,
  type SelectOption,
} from '@/shared/ui';
import type { ConstantPartyStorageLog } from '@/shared/api/constantPartiesApi';

defineProps<{
  logs: ConstantPartyStorageLog[];
  loading: boolean;
  currentPage: number;
  lastPage: number;
  dateRange: DateRange;
  sort: 'asc' | 'desc';
}>();

const emit = defineEmits<{
  (event: 'update:dateRange', value: DateRange): void;
  (event: 'update:sort', value: 'asc' | 'desc'): void;
  (event: 'page-change', page: number): void;
}>();

const sortOptions: SelectOption[] = [
  { value: 'desc', label: 'Сначала новые' },
  { value: 'asc', label: 'Сначала старые' },
];

function actionIcon(action: ConstantPartyStorageLog['action']) {
  if (action === 'item_created') return PackagePlus;
  if (action === 'item_deleted') return Trash2;
  if (action === 'item_renamed') return Pencil;
  if (action === 'quantity_changed') return Warehouse;
  if (action === 'item_granted') return Gift;
  if (action === 'grant_revoked') return RotateCcw;
  if (action === 'member_joined') return UserPlus;
  if (action === 'member_left') return LogOut;
  if (action === 'member_removed') return UserMinus;
  return History;
}

function actionLabel(action: ConstantPartyStorageLog['action']) {
  if (action === 'item_created') return 'Добавление';
  if (action === 'item_deleted') return 'Удаление';
  if (action === 'item_renamed') return 'Переименование';
  if (action === 'quantity_changed') return 'Изменение остатка';
  if (action === 'item_granted') return 'Выдача';
  if (action === 'grant_revoked') return 'Отмена выдачи';
  if (action === 'member_joined') return 'Вступление';
  if (action === 'member_left') return 'Выход';
  if (action === 'member_removed') return 'Исключение';
  return 'Действие';
}

function actionClass(action: ConstantPartyStorageLog['action']) {
  if (action === 'item_created' || action === 'member_joined') {
    return 'border-emerald-600/25 bg-emerald-500/5 text-emerald-700 dark:text-emerald-400';
  }
  if (action === 'item_deleted' || action === 'member_removed') {
    return 'border-destructive/25 bg-destructive/5 text-destructive';
  }
  if (action === 'item_granted') {
    return 'border-primary/25 bg-primary/5 text-primary';
  }
  if (action === 'grant_revoked' || action === 'member_left') {
    return 'border-amber-600/25 bg-amber-500/5 text-amber-700 dark:text-amber-400';
  }
  return 'border-border text-muted-foreground';
}

function quantity(value: Record<string, unknown> | null) {
  const current = value?.quantity;
  if (current === null) return 'без ограничения';
  if (typeof current === 'number') return current.toLocaleString('ru-RU');
  return '—';
}

function grantedQuantity(log: ConstantPartyStorageLog) {
  const value = log.metadata?.quantity;
  return typeof value === 'number' ? value.toLocaleString('ru-RU') : '1';
}

function description(log: ConstantPartyStorageLog) {
  const characterName = log.recipient_character_name ?? 'Персонаж';

  if (log.action === 'item_created') {
    return `Добавлен предмет «${log.item_name}», остаток: ${quantity(log.new_value)}.`;
  }
  if (log.action === 'item_deleted') {
    return `Удалён предмет «${log.item_name}».`;
  }
  if (log.action === 'item_renamed') {
    const oldName = String(log.old_value?.name ?? log.item_name);
    const newName = String(log.new_value?.name ?? log.item_name);
    return `«${oldName}» переименован в «${newName}».`;
  }
  if (log.action === 'quantity_changed') {
    return `Остаток «${log.item_name}»: ${quantity(log.old_value)} → ${quantity(log.new_value)}.`;
  }
  if (log.action === 'item_granted') {
    return `${grantedQuantity(log)} шт. «${log.item_name}» выдано персонажу ${characterName}.`;
  }
  if (log.action === 'grant_revoked') {
    return `Выдача ${grantedQuantity(log)} шт. «${log.item_name}» персонажу ${characterName} отменена.`;
  }
  if (log.action === 'member_joined') {
    return `Персонаж ${characterName} вступил в КП.`;
  }
  if (log.action === 'member_left') {
    return `Персонаж ${characterName} вышел из КП.`;
  }
  if (log.action === 'member_removed') {
    return log.metadata?.source === 'server_changed'
      ? `Персонаж ${characterName} исключён из КП из-за смены сервера.`
      : `Персонаж ${characterName} исключён из КП.`;
  }
  return log.item_name;
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU');
}

function formatTime(value: string) {
  return new Date(value).toLocaleTimeString('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}
</script>

<template>
  <section class="overflow-hidden rounded-lg border bg-background">
    <header class="flex items-center gap-3 border-b px-4 py-4 sm:px-5">
      <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
        <History class="size-4" />
      </span>
      <div>
        <h2 class="font-semibold">Журнал КП</h2>
        <p class="text-sm text-muted-foreground">Последние действия участников</p>
      </div>
    </header>

    <div class="grid gap-2 border-b px-4 py-3 sm:grid-cols-[15rem_12rem] sm:px-5">
      <DateRangePicker
        :model-value="dateRange"
        @update:model-value="emit('update:dateRange', $event)"
      />
      <Select
        :model-value="sort"
        :options="sortOptions"
        trigger-class="w-full"
        @update:model-value="emit('update:sort', $event as 'asc' | 'desc')"
      />
    </div>

    <div
      v-if="logs.length === 0 && loading"
      class="flex justify-center py-12"
    >
      <Spinner class="size-6" />
    </div>
    <div
      v-else-if="logs.length === 0"
      class="px-4 py-12 text-center text-sm text-muted-foreground"
    >
      Действий за выбранный период нет.
    </div>
    <div
      v-else
      :class="{ 'opacity-60': loading }"
    >
      <article
        v-for="log in logs"
        :key="log.id"
        class="flex gap-3 border-b px-4 py-4 last:border-b-0 sm:gap-4 sm:px-5"
      >
        <span
          class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg border"
          :class="actionClass(log.action)"
        >
          <component
            :is="actionIcon(log.action)"
            class="size-4"
          />
        </span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-medium">{{ log.actor_character_name }}</span>
            <Badge
              variant="outline"
              :class="actionClass(log.action)"
            >
              {{ actionLabel(log.action) }}
            </Badge>
          </div>
          <p class="mt-1.5 break-words text-sm">
            {{ description(log) }}
          </p>
          <p
            v-if="log.metadata?.reason"
            class="mt-1 text-xs text-muted-foreground"
          >
            Причина: {{ log.metadata.reason }}
          </p>
        </div>

        <time
          :datetime="log.created_at"
          class="shrink-0 text-right text-xs text-muted-foreground"
        >
          <span class="block whitespace-nowrap">{{ formatDate(log.created_at) }}</span>
          <span class="mt-0.5 block whitespace-nowrap opacity-75">{{ formatTime(log.created_at) }}</span>
        </time>
      </article>
    </div>

    <footer
      v-if="lastPage > 1"
      class="flex items-center justify-center gap-3 border-t px-4 py-3"
    >
      <Button
        type="button"
        variant="outline"
        size="icon"
        :disabled="loading || currentPage <= 1"
        title="Предыдущая страница"
        aria-label="Предыдущая страница"
        @click="emit('page-change', currentPage - 1)"
      >
        <ChevronLeft class="size-4" />
      </Button>
      <span class="min-w-20 text-center text-sm text-muted-foreground">
        {{ currentPage }} из {{ lastPage }}
      </span>
      <Button
        type="button"
        variant="outline"
        size="icon"
        :disabled="loading || currentPage >= lastPage"
        title="Следующая страница"
        aria-label="Следующая страница"
        @click="emit('page-change', currentPage + 1)"
      >
        <ChevronRight class="size-4" />
      </Button>
    </footer>
  </section>
</template>
