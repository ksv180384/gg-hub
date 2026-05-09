<script setup lang="ts">
import { ref, computed, shallowRef } from 'vue';
import { Sortable } from 'sortablejs-vue3';
import { Button } from '@/shared/ui';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  Tooltip,
} from '@/shared/ui';
import type { RaidItem } from '@/shared/api/guildsApi';

const MAX_RAID_DEPTH = 5;

const props = defineProps<{
  raid: RaidItem;
  /** Глубина в дереве (0 = корень). */
  depth?: number;
  /** Сумма участников (своих + дочерних рейдов). */
  totalMembers?: number;
  /** Карта raidId -> totalMembers для дочерних рейдов. */
  raidTotalMembersMap?: Record<string, number>;
  canEdit: boolean;
  canDelete: boolean;
  /** id выбранного рейда (для подсветки активного). */
  selectedRaidId?: number | null;
  /** Опции Sortable (sortablejs-vue3) для корня и вложенных списков. */
  sortableOptions?: Record<string, unknown>;
  /** Ключ для пересоздания вложенного Sortable после дропа (убирает дубликат). */
  sortableKey?: number;
}>();

const emit = defineEmits<{
  (e: 'add-child', parentId: number): void;
  (e: 'edit', raid: RaidItem): void;
  (e: 'delete', raid: RaidItem): void;
  (e: 'select', raid: RaidItem): void;
  (e: 'sort-end', evt: { item: HTMLElement; to: HTMLElement }): void;
}>();

const expanded = ref(true);
const hasChildren = computed(() => (props.raid.children?.length ?? 0) > 0);
/** Стабильный пустой массив для Sortable, когда у рейда ещё нет детей (чтобы не менять ссылку каждый рендер). */
const emptyChildren = shallowRef<RaidItem[]>([]);
const childrenList = computed(() => props.raid.children ?? emptyChildren.value);
const isSelected = computed(() => props.selectedRaidId != null && props.raid.id === props.selectedRaidId);
/** Рейд с участниками не может иметь дочерних; макс. вложенность 5 уровней. */
const canAddChild = computed(() => {
  const d = props.depth ?? 0;
  return props.canEdit && ((props.raid.members_count ?? 0) === 0) && d < MAX_RAID_DEPTH - 1;
});

/** Корректное склонение слова «участник». */
const totalMembersTitle = computed(() => {
  const n = props.totalMembers ?? 0;
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return `${n} участник всего`;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return `${n} участника всего`;
  return `${n} участников всего`;
});
</script>

<template>
  <li class="raid-tree-item relative list-none" :data-raid-id="raid.id">
    <span class="raid-tree-dot bg-[#e5e5e5] shadow-[0_0_3px_2px_oklch(0.83_0_0)] dark:bg-zinc-500 dark:shadow-[0_0_4px_2px_rgba(0,0,0,0.55)]" aria-hidden="true" />
    <!-- Карточка рейда -->
    <div
      role="button"
      tabindex="0"
      class="raid-tree-card group relative rounded-2xl border border-border/60 bg-[#fafafa] px-2.5 py-2 text-neutral-900 shadow-[1px_1px_2px_0px_#d9d9d9] transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background sm:px-3 sm:py-2.5 dark:bg-card dark:text-card-foreground dark:shadow-[1px_1px_4px_0px_rgba(0,0,0,0.45)]"
      :class="[
        isSelected
          ? 'border-primary/60 shadow-sm dark:border-primary/50 dark:bg-primary/15 dark:shadow-md'
          : 'hover:border-border dark:hover:border-border dark:hover:bg-accent/35',
      ]"
      @click="emit('select', raid)"
      @keydown.enter="emit('select', raid)"
      @keydown.space.prevent="emit('select', raid)"
    >
      <div class="flex items-center gap-1.5 sm:gap-2">
        <!-- Drag handle (внутри карточки) -->
        <span
          v-if="canEdit && sortableOptions"
          class="raid-drag-handle flex h-8 w-5 sm:w-6 shrink-0 cursor-grab items-center justify-center rounded text-neutral-500 hover:text-neutral-900 active:cursor-grabbing dark:text-muted-foreground dark:hover:text-foreground"
          aria-hidden="true"
          @click.stop
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="6" r="1.5" />
            <circle cx="9" cy="12" r="1.5" />
            <circle cx="9" cy="18" r="1.5" />
            <circle cx="15" cy="6" r="1.5" />
            <circle cx="15" cy="12" r="1.5" />
            <circle cx="15" cy="18" r="1.5" />
          </svg>
        </span>
        <span v-else-if="sortableOptions" class="w-5 sm:w-6 shrink-0" />

        <!-- Кнопка раскрытия (показывается только при наличии дочерних) -->
        <button
          v-if="hasChildren"
          type="button"
          class="hidden sm:flex h-6 w-6 shrink-0 items-center justify-center rounded text-neutral-500 hover:bg-neutral-200/80 dark:text-muted-foreground dark:hover:bg-muted"
          :aria-label="expanded ? 'Свернуть' : 'Развернуть'"
          @click.stop="expanded = !expanded"
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
            class="transition-transform"
            :class="expanded ? '' : '-rotate-90'"
          >
            <polyline points="6 9 12 15 18 9" />
          </svg>
        </button>

        <!-- Контент: название + лидер/описание -->
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-1.5 min-w-0">
            <span
              class="min-w-0 truncate font-medium leading-5 text-neutral-900 dark:text-card-foreground"
              :title="raid.name"
            >
              {{ raid.name }}
            </span>
            <span
              v-if="raid.members_count != null && raid.members_count > 0"
              class="inline-flex shrink-0 items-center rounded-full border border-border/50 bg-white/70 px-2 py-0.5 text-[11px] text-neutral-600 tabular-nums dark:border-border/60 dark:bg-background/40 dark:text-muted-foreground"
              :title="`Своих участников: ${raid.members_count}`"
            >
              своих: {{ raid.members_count }}
            </span>
          </div>
          <div
            v-if="raid.leader || raid.description"
            class="mt-0.5 flex min-w-0 flex-nowrap items-center gap-2 overflow-hidden text-xs text-neutral-600 dark:text-muted-foreground"
          >
            <span
              v-if="raid.leader"
              class="inline-flex min-w-0 shrink-0 max-w-[45%] items-center gap-1"
              :title="raid.leader.name"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="12"
                height="12"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="shrink-0 opacity-70"
                aria-hidden="true"
              >
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
              <span class="min-w-0 truncate">Лидер: <span class="font-semibold text-neutral-900 dark:text-card-foreground">{{ raid.leader.name }}</span></span>
            </span>
            <Tooltip
              v-if="raid.description"
              :content="raid.description"
              class="max-w-md whitespace-pre-wrap break-words"
            >
              <span
                class="inline-flex min-w-0 shrink items-center overflow-hidden rounded-md border border-transparent bg-neutral-200 px-2 py-0.5 dark:border-border/50 dark:bg-muted"
                @click.stop
              >
                <span class="block min-w-0 truncate text-neutral-800 dark:text-foreground/95">{{ raid.description }}</span>
              </span>
            </Tooltip>
          </div>
        </div>

        <!-- Бейдж количества участников + меню -->
        <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
          <span
            v-if="totalMembers != null"
            class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full border border-border/50 bg-white/80 px-2.5 text-xs text-neutral-800 tabular-nums dark:border-border/60 dark:bg-background/50 dark:text-card-foreground"
            :title="totalMembersTitle"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="12"
              height="12"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            {{ totalMembers }}
          </span>
          <DropdownMenu v-if="canEdit || canDelete">
            <DropdownMenuTrigger as-child>
              <Button
                variant="ghost"
                size="sm"
                class="h-8 w-8 shrink-0 p-0 opacity-70 hover:opacity-100"
                aria-label="Действия"
                @click.stop
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="5" r="1" />
                  <circle cx="12" cy="12" r="1" />
                  <circle cx="12" cy="19" r="1" />
                </svg>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem v-if="canAddChild" @click="emit('add-child', raid.id)">
                Добавить подрейд
              </DropdownMenuItem>
              <DropdownMenuItem v-if="canEdit" @click="emit('edit', raid)">
                Редактировать
              </DropdownMenuItem>
              <DropdownMenuItem
                v-if="canDelete"
                class="text-destructive focus:text-destructive"
                @click="emit('delete', raid)"
              >
                Удалить
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>

    <!-- Дочерние рейды (с L-образными коннекторами через CSS) -->
    <Sortable
      v-if="(canAddChild || hasChildren) && expanded"
      :key="sortableKey ?? 0"
      :list="childrenList"
      item-key="id"
      tag="ul"
      class="raid-tree-children mt-1.5 ml-5 sm:ml-7 pl-4 sm:pl-5"
      :data-parent-id="String(raid.id)"
      :options="sortableOptions ?? {}"
      @end="(evt: { item: HTMLElement; to: HTMLElement }) => emit('sort-end', evt)"
    >
      <template #item="{ element }">
        <RaidTreeItem
          :raid="element"
          :depth="(depth ?? 0) + 1"
          :total-members="raidTotalMembersMap?.[String(element.id)] ?? 0"
          :raid-total-members-map="raidTotalMembersMap"
          :can-edit="canEdit"
          :can-delete="canDelete"
          :selected-raid-id="selectedRaidId"
          :sortable-options="sortableOptions"
          :sortable-key="sortableKey"
          @add-child="emit('add-child', $event)"
          @edit="emit('edit', $event)"
          @delete="emit('delete', $event)"
          @select="emit('select', $event)"
          @sort-end="emit('sort-end', $event)"
        />
      </template>
    </Sortable>
  </li>
</template>

<style scoped>
.raid-tree-item {
  position: relative;
}

.raid-tree-children {
  position: relative;
  list-style: none;
  margin-bottom: 0.3rem;
}

/* Точка на месте стыка L-образного коннектора с карточкой рейда. По умолчанию скрыта — */
/* показывается только у вложенных рейдов (внутри .raid-tree-children). */
.raid-tree-dot {
  display: none;
}

.raid-tree-children > .raid-tree-item > .raid-tree-dot {
  display: block;
  position: absolute;
  left: -0.02rem;
  top: 1.6rem;
  width: 0.1rem;
  height: 0.1rem;
  border-radius: 9999px;
  pointer-events: none;
  z-index: 1;
}

/* Промежутки между дочерними рейдами */
.raid-tree-children > .raid-tree-item + .raid-tree-item {
  margin-top: 0.375rem;
}

/* L-образный коннектор от родителя к каждому ребёнку */
.raid-tree-children > .raid-tree-item::before {
  content: '';
  position: absolute;
  /* Сдвигаем за пределы li, чтобы оказаться слева в зоне padding-left родителя */
  left: -1rem;
  top: -0.3rem;
  width: 1rem;
  height: 2rem;
  border-left: 1.5px solid var(--border);
  border-bottom: 1.5px solid var(--border);
  border-bottom-left-radius: 12px;
  pointer-events: none;
}

@media (min-width: 640px) {
  .raid-tree-children > .raid-tree-item::before {
    left: -1.25rem;
    width: 1.25rem;
  }
}

/* Продолжение вертикальной линии вниз (только если ребёнок не последний) */
.raid-tree-children > .raid-tree-item:not(:last-child)::after {
  content: '';
  position: absolute;
  left: -1rem;
  top: 1.15rem;
  bottom: 0;
  width: 0;
  border-left: 1.5px solid var(--border);
  pointer-events: none;
}

@media (min-width: 640px) {
  .raid-tree-children > .raid-tree-item:not(:last-child)::after {
    left: -1.25rem;
  }
}
</style>
