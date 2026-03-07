<script setup lang="ts">
import { ref, computed, shallowRef } from 'vue';
import { Sortable } from 'sortablejs-vue3';
import { Button } from '@/shared/ui';
import {
  Badge,
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
      class="flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2 transition-colors hover:bg-muted/40 cursor-pointer"
      :class="[
        raid.parent_id ? 'mt-1 border-l-2 border-primary/40' : '',
        isSelected
          ? 'border-primary bg-primary/10 ring-2 ring-primary/30'
          : 'border-border/60 bg-card',
      ]"
      @click="emit('select', raid)"
      @keydown.enter="emit('select', raid)"
      @keydown.space.prevent="emit('select', raid)"
    >
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
        class="flex h-7 w-7 shrink-0 items-center justify-center rounded hover:bg-muted"
        :aria-label="expanded ? 'Свернуть' : 'Развернуть'"
        @click.stop="expanded = !expanded"
      >
        <span
          class="text-muted-foreground transition-transform"
          :class="expanded ? '' : '-rotate-90'"
        >
          ▼
        </span>
      </button>
      <span v-else class="w-7 shrink-0" />
      <span class="min-w-0 flex-1 font-medium">{{ raid.name }}</span>
      <span v-if="totalMembers != null" class="shrink-0 text-xs text-muted-foreground">
        ({{ totalMembers }} {{ totalMembers === 1 ? 'участник' : totalMembers > 1 && totalMembers < 5 ? 'участника' : 'участников' }})
      </span>
      <Badge v-if="raid.leader" variant="secondary" class="shrink-0 text-xs">
        Лидер: {{ raid.leader.name }}
      </Badge>
      <DropdownMenu v-if="canEdit || canDelete">
        <DropdownMenuTrigger as-child>
          <Button variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" aria-label="Действия" @click.stop>
            ⋮
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
    <!-- Показываем список дочерних и зону дропа, если рейд может иметь детей или уже имеет (иначе нельзя перетащить рейд «в подрейд») -->
    <Sortable
      v-if="(canAddChild || hasChildren) && expanded"
      :key="sortableKey ?? 0"
      :list="childrenList"
      item-key="id"
      tag="ul"
      class="raid-tree mt-1 space-y-0 border-l border-border/60 pl-8"
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
