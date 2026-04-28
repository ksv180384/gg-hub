<script setup lang="ts">
import { ref, computed, shallowRef } from 'vue';
import { Sortable } from 'sortablejs-vue3';
import { Button } from '@/shared/ui';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
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
</script>

<template>
  <li class="list-none pb-1" :data-raid-id="raid.id">
    <div
      role="button"
      tabindex="0"
      class="group relative rounded-xl border px-3 py-2.5 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
      :class="[
        raid.parent_id ? 'mt-1 border-l-2 border-primary/40' : '',
        isSelected
          ? 'border-primary bg-primary/5 shadow-sm'
          : 'border-border/60 bg-card hover:bg-accent/50 hover:border-border',
      ]"
      @click="emit('select', raid)"
      @keydown.enter="emit('select', raid)"
      @keydown.space.prevent="emit('select', raid)"
    >
      <div class="flex items-start gap-2">
        <span
          v-if="canEdit && sortableOptions"
          class="raid-drag-handle flex h-7 w-7 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
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
        <span v-else-if="sortableOptions" class="w-7 shrink-0" />
        <button
          v-if="hasChildren"
          type="button"
          class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-muted"
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
        <span v-else class="w-7 shrink-0" />
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2 min-w-0">
            <span class="min-w-0 truncate font-medium leading-5" :title="raid.name">{{ raid.name }}</span>
            <span
              v-if="raid.members_count != null && raid.members_count > 0"
              class="inline-flex shrink-0 items-center rounded-full border bg-background/60 px-2 py-0.5 text-[11px] text-muted-foreground tabular-nums"
              :title="`Своих участников: ${raid.members_count}`"
            >
              свои: {{ raid.members_count }}
            </span>
          </div>
          <div class="mt-1 flex flex-wrap items-center gap-1.5 pl-0.5 text-xs text-muted-foreground">
            <span
              v-if="raid.leader"
              class="inline-flex min-w-0 items-center gap-1 rounded-md bg-muted px-2 py-0.5"
              :title="raid.leader.name"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                <path d="M2 20l3-13 5 4 2-7 2 7 5-4 3 13z" />
                <path d="M2 20h18" />
              </svg>
              <span class="truncate">Лидер: {{ raid.leader.name }}</span>
            </span>
            <span
              v-if="raid.description"
              class="inline-flex min-w-0 max-w-full items-center rounded-md bg-muted/60 px-2 py-0.5"
              :title="raid.description"
            >
              <span class="truncate">{{ raid.description }}</span>
            </span>
          </div>
        </div>
        <div class="flex items-center gap-1.5">
          <span
            v-if="totalMembers != null"
            class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full border bg-background px-2.5 text-xs text-foreground/80 tabular-nums"
            :title="`${totalMembers} ${totalMembers === 1 ? 'участник' : totalMembers > 1 && totalMembers < 5 ? 'участника' : 'участников'} всего`"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                class="h-8 w-8 shrink-0 p-0 opacity-80 hover:opacity-100"
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
    <!-- Показываем список дочерних и зону дропа, если рейд может иметь детей или уже имеет (иначе нельзя перетащить рейд «в подрейд») -->
    <Sortable
      v-if="(canAddChild || hasChildren) && expanded"
      :key="sortableKey ?? 0"
      :list="childrenList"
      item-key="id"
      tag="ul"
      class="raid-tree mt-1 space-y-0 border-l border-border/60 ml-8 pl-4"
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
