<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
} from 'radix-vue';
import ClientOnly from '@/shared/ui/ClientOnly.vue';
import {
  Button,
  Input,
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  Badge,
} from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import ConfirmDialog from '@/shared/ui/confirm-dialog/ConfirmDialog.vue';
import {
  guildsApi,
  type Guild,
  type RaidItem,
  type RaidMemberItem,
  type GuildRosterMember,
  type CreateRaidPayload,
  type UpdateRaidPayload,
  type RaidCompositionMemberPayload,
} from '@/shared/api/guildsApi';
import { Sortable } from 'sortablejs-vue3';
import RaidTreeItem from './RaidTreeItem.vue';
import FormRaidModal from './FormRaidModal.vue';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const raids = ref<RaidItem[]>([]);
const roster = ref<GuildRosterMember[]>([]);
const loading = ref(true);
const accessDenied = ref(false);
const error = ref<string | null>(null);

/** Выбранный рейд (при клике по рейду загружаются детали с участниками). */
const selectedRaidId = ref<number | null>(null);
const selectedRaid = ref<RaidItem | null>(null);
const selectedRaidLoading = ref(false);
/** Состояние выезжающей панели деталей рейда (drawer справа). */
const raidSheetOpen = ref(false);

const canFormRaid = computed(
  () => guild.value?.my_permission_slugs?.includes('formirovat-reidy') ?? false
);
const canDeleteRaid = computed(
  () => guild.value?.my_permission_slugs?.includes('udaliat-reidy') ?? false
);

/** Размер пати из параметров игры (число ячеек в ряду). */
const partySize = computed(() => guild.value?.game?.party_size ?? 5);

/** Модалка формирования рейда (полный экран). */
const formRaidModalOpen = ref(false);
const formRaidSaving = ref(false);

function openFormRaidModal() {
  if (selectedRaid.value == null) return;
  formRaidModalOpen.value = true;
}

function closeFormRaidModal() {
  formRaidModalOpen.value = false;
}

async function saveFormRaid(members: RaidCompositionMemberPayload[]) {
  if (selectedRaidId.value == null) return;
  formRaidSaving.value = true;
  try {
    const updated = await guildsApi.setRaidComposition(guildId.value, selectedRaidId.value, members);
    selectedRaid.value = updated;
  } finally {
    formRaidSaving.value = false;
  }
}

// Форма создания/редактирования (модальное окно)
const modalOpen = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formParentId = ref<string>('__none__');
const formRaidId = ref<number | null>(null);
const formName = ref('');
const formDescription = ref('');
const formLeaderId = ref<string>('__none__');
/** Поиск в списке лидеров (фильтр по имени). */
const leaderSearchQuery = ref('');
const formSubmitting = ref(false);
const formError = ref<string | null>(null);

/** Участники состава, отфильтрованные по поиску лидера. */
const leaderOptionsFiltered = computed(() => {
  const q = leaderSearchQuery.value.trim().toLowerCase();
  if (!q) return roster.value;
  return roster.value.filter((m) => m.name.toLowerCase().includes(q));
});

// Список рейдов для выбора родителя (плоский, без циклов)
function flattenRaids(items: RaidItem[], excludeId?: number): { id: number; name: string; depth: number; members_count: number }[] {
  const out: { id: number; name: string; depth: number; members_count: number }[] = [];
  function walk(list: RaidItem[], depth: number) {
    for (const r of list) {
      if (r.id === excludeId) continue;
      out.push({ id: r.id, name: r.name, depth, members_count: r.members_count ?? 0 });
      if (r.children?.length) walk(r.children, depth + 1);
    }
  }
  walk(items, 0);
  return out;
}

/** Значение для SelectItem «без выбора» (Radix не допускает value=""). */
const SELECT_NONE = '__none__';

const parentOptions = computed(() => {
  const flat = flattenRaids(raids.value, formRaidId.value ?? undefined);
  return [
    { id: SELECT_NONE, name: '— Без родителя (корневой рейд) —', depth: 0, members_count: 0 },
    ...flat.map((r) => ({ id: String(r.id), name: r.name, depth: r.depth, members_count: r.members_count })),
  ];
});

function openCreate(parentId: number | null = null) {
  formMode.value = 'create';
  formRaidId.value = null;
  formParentId.value = parentId != null ? String(parentId) : SELECT_NONE;
  formName.value = '';
  formDescription.value = '';
  formLeaderId.value = SELECT_NONE;
  leaderSearchQuery.value = '';
  formError.value = null;
  modalOpen.value = true;
}

function openEdit(raid: RaidItem) {
  formMode.value = 'edit';
  formRaidId.value = raid.id;
  formParentId.value = raid.parent_id != null ? String(raid.parent_id) : SELECT_NONE;
  formName.value = raid.name;
  formDescription.value = raid.description ?? '';
  formLeaderId.value = raid.leader_character_id != null ? String(raid.leader_character_id) : SELECT_NONE;
  leaderSearchQuery.value = '';
  formError.value = null;
  modalOpen.value = true;
}

async function submitForm() {
  formError.value = null;
  if (!formName.value.trim()) {
    formError.value = 'Введите название рейда.';
    return;
  }
  formSubmitting.value = true;
  try {
    if (formMode.value === 'create') {
      const payload: CreateRaidPayload = {
        name: formName.value.trim(),
        description: formDescription.value.trim() || null,
        parent_id: formParentId.value && formParentId.value !== SELECT_NONE ? Number(formParentId.value) : null,
        leader_character_id: formLeaderId.value && formLeaderId.value !== SELECT_NONE ? Number(formLeaderId.value) : null,
      };
      await guildsApi.createGuildRaid(guildId.value, payload);
    } else {
      const payload: UpdateRaidPayload = {
        name: formName.value.trim(),
        description: formDescription.value.trim() || null,
        parent_id: formParentId.value && formParentId.value !== SELECT_NONE ? Number(formParentId.value) : null,
        leader_character_id: formLeaderId.value && formLeaderId.value !== SELECT_NONE ? Number(formLeaderId.value) : null,
      };
      await guildsApi.updateGuildRaid(guildId.value, formRaidId.value!, payload);
    }
    modalOpen.value = false;
    await loadRaids();
  } catch (e: unknown) {
    const err = e as Error & { message?: string };
    formError.value = err?.message ?? 'Ошибка сохранения.';
  } finally {
    formSubmitting.value = false;
  }
}

// Удаление
const deleteDialogOpen = ref(false);
const deleteRaidId = ref<number | null>(null);
const deleteLoading = ref(false);

function openDelete(raid: RaidItem) {
  deleteRaidId.value = raid.id;
  deleteDialogOpen.value = true;
}

async function confirmDelete() {
  if (deleteRaidId.value == null) return;
  deleteLoading.value = true;
  try {
    await guildsApi.deleteGuildRaid(guildId.value, deleteRaidId.value);
    deleteDialogOpen.value = false;
    deleteRaidId.value = null;
    await loadRaids();
  } catch {
    deleteLoading.value = false;
  } finally {
    deleteLoading.value = false;
  }
}

function closeRaidModal() {
  modalOpen.value = false;
}

async function loadRaids() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  try {
    raids.value = await guildsApi.getGuildRaids(guildId.value);
  } catch {
    raids.value = [];
  }
}

/** Поиск по имени участника в панели рейда. */
const memberSearchQuery = ref('');

/**
 * Кэш загруженных деталей рейдов (для агрегации по дочерним).
 * Ключ — id рейда, значение — полный RaidItem с members/leader.
 */
const raidDetailsMap = ref<Map<number, RaidItem>>(new Map());

/** Лидер с указанием рейда, к которому он относится. */
interface AggregatedLeader {
  character_id: number;
  name: string;
  raid_id: number;
  raid_name: string;
}

/** Участник с указанием рейда, к которому он относится. */
interface AggregatedMember extends RaidMemberItem {
  raid_id: number;
  raid_name: string;
}

/** Элемент объединённого списка участников: либо лидер рейда, либо обычный участник. */
interface UnifiedMember {
  character_id: number;
  name: string;
  raid_id: number;
  raid_name: string;
  role?: string | null;
  /** Название рейда, в котором данный персонаж является лидером. Null — обычный участник. */
  leader_of: string | null;
}

/** Собрать все id рейда и всех его потомков. */
function collectDescendantIds(node: RaidItem | null): number[] {
  if (!node) return [];
  const out: number[] = [];
  function walk(r: RaidItem) {
    out.push(r.id);
    r.children?.forEach(walk);
  }
  walk(node);
  return out;
}

/** Лидеры выбранного рейда и всех его дочерних рейдов (в порядке обхода сверху вниз). */
const aggregatedLeaders = computed((): AggregatedLeader[] => {
  const root = selectedRaidInTree.value;
  if (!root) return [];
  const out: AggregatedLeader[] = [];
  function walk(r: RaidItem) {
    const detail = raidDetailsMap.value.get(r.id);
    const leader = detail?.leader ?? r.leader;
    if (leader) {
      out.push({
        character_id: leader.id,
        name: leader.name,
        raid_id: r.id,
        raid_name: r.name,
      });
    }
    r.children?.forEach(walk);
  }
  walk(root);
  return out;
});

/** Участники выбранного рейда и всех его дочерних рейдов с пометкой рейда. */
const aggregatedMembers = computed((): AggregatedMember[] => {
  const root = selectedRaidInTree.value;
  if (!root) return [];
  const out: AggregatedMember[] = [];
  function walk(r: RaidItem) {
    const detail = raidDetailsMap.value.get(r.id);
    detail?.members?.forEach((m) => {
      out.push({ ...m, raid_id: r.id, raid_name: r.name });
    });
    r.children?.forEach(walk);
  }
  walk(root);
  return out;
});

/**
 * Общий список: сначала лидеры рейдов (с пометкой «Лидер рейда «X»»), затем
 * обычные участники. Участник, являющийся лидером своего же рейда, не дублируется.
 */
const unifiedMembers = computed((): UnifiedMember[] => {
  const leaderEntries: UnifiedMember[] = aggregatedLeaders.value.map((l) => ({
    character_id: l.character_id,
    name: l.name,
    raid_id: l.raid_id,
    raid_name: l.raid_name,
    role: null,
    leader_of: l.raid_name,
  }));
  const leaderKeys = new Set(
    leaderEntries.map((l) => `${l.raid_id}:${l.character_id}`)
  );
  const memberEntries: UnifiedMember[] = aggregatedMembers.value
    .filter((m) => !leaderKeys.has(`${m.raid_id}:${m.character_id}`))
    .map((m) => ({
      character_id: m.character_id,
      name: m.name,
      raid_id: m.raid_id,
      raid_name: m.raid_name,
      role: m.role ?? null,
      leader_of: null,
    }));
  return [...leaderEntries, ...memberEntries];
});

/** Общий список, отфильтрованный по поиску (по имени и названию рейда). */
const filteredUnifiedMembers = computed((): UnifiedMember[] => {
  const q = memberSearchQuery.value.trim().toLowerCase();
  if (!q) return unifiedMembers.value;
  return unifiedMembers.value.filter(
    (m) => m.name.toLowerCase().includes(q) || m.raid_name.toLowerCase().includes(q)
  );
});

async function selectRaid(raid: RaidItem) {
  raidSheetOpen.value = true;
  if (selectedRaidId.value === raid.id) return;
  selectedRaidId.value = raid.id;
  selectedRaidLoading.value = true;
  selectedRaid.value = null;
  memberSearchQuery.value = '';
  raidDetailsMap.value = new Map();

  const raidInTree = findRaidById(raids.value, raid.id);
  const allIds = collectDescendantIds(raidInTree);
  if (allIds.length === 0) allIds.push(raid.id);

  try {
    const results = await Promise.allSettled(
      allIds.map((id) => guildsApi.getGuildRaid(guildId.value, id))
    );
    const map = new Map<number, RaidItem>();
    for (const r of results) {
      if (r.status === 'fulfilled' && r.value) map.set(r.value.id, r.value);
    }
    raidDetailsMap.value = map;
    selectedRaid.value = map.get(raid.id) ?? null;
  } catch {
    selectedRaid.value = null;
  } finally {
    selectedRaidLoading.value = false;
  }
}

function clearSelectedRaid() {
  selectedRaidId.value = null;
  selectedRaid.value = null;
  raidDetailsMap.value = new Map();
}

/** Инициалы для fallback аватара лидера. */
function leaderInitials(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

watch(raidSheetOpen, (open) => {
  if (!open) clearSelectedRaid();
});

function findRaidById(items: RaidItem[], id: number): RaidItem | null {
  for (const r of items) {
    if (r.id === id) return r;
    const found = r.children?.length ? findRaidById(r.children, id) : null;
    if (found) return found;
  }
  return null;
}

/** Выбранный рейд в дереве (для проверки наличия дочерних). */
const selectedRaidInTree = computed(() =>
  selectedRaidId.value != null ? findRaidById(raids.value, selectedRaidId.value) : null
);
const selectedRaidHasChildren = computed(() => (selectedRaidInTree.value?.children?.length ?? 0) > 0);

/** Все id в поддереве рейда (включая сам рейд). Для проверки цикла при переносе. */
function getDescendantIds(items: RaidItem[], targetId: number): Set<number> {
  const result = new Set<number>();
  function walk(list: RaidItem[]): boolean {
    for (const r of list) {
      if (r.id === targetId) {
        collect(r);
        return true;
      }
      if (r.children?.length && walk(r.children)) return true;
    }
    return false;
  }
  function collect(r: RaidItem) {
    result.add(r.id);
    r.children?.forEach((c) => collect(c));
  }
  walk(items);
  return result;
}

const dragSaving = ref(false);
const isDraggingRaid = ref(false);
/** Ключ корневого Sortable: после дропа увеличиваем, чтобы пересоздать список и убрать дубликат. */
const raidSortableKey = ref(0);

/** Находит рейд в дереве по id и удаляет из массива, возвращает найденный рейд или null. */
function findAndRemoveRaid(items: RaidItem[], raidId: number): RaidItem | null {
  const i = items.findIndex((r) => r.id === raidId);
  if (i !== -1) {
    const [removed] = items.splice(i, 1);
    return removed;
  }
  for (const r of items) {
    if (r.children) {
      const found = findAndRemoveRaid(r.children, raidId);
      if (found) return found;
    }
  }
  return null;
}

/** Находит рейд по id в дереве (без удаления). */
function findRaidInTree(items: RaidItem[], raidId: number): RaidItem | null {
  for (const r of items) {
    if (r.id === raidId) return r;
    if (r.children) {
      const found = findRaidInTree(r.children, raidId);
      if (found) return found;
    }
  }
  return null;
}

/** Обработчик дропа рейда (sortablejs-vue3 передаёт событие с item, to и from). */
async function handleRaidDrop(evt: { item: HTMLElement; to: HTMLElement; from?: HTMLElement }) {
  const movedRaidId = Number(evt.item.getAttribute('data-raid-id'));
  if (!Number.isInteger(movedRaidId)) return;
  const toEl = evt.to as HTMLElement;
  const parentIdAttr = toEl.getAttribute?.('data-parent-id') ?? toEl.parentElement?.getAttribute?.('data-parent-id') ?? '';
  const parentId = parentIdAttr === '' ? null : Number(parentIdAttr);
  const descendantIds = getDescendantIds(raids.value, movedRaidId);
  if (parentId !== null && descendantIds.has(parentId)) {
    await loadRaids();
    return;
  }
  const raidIdsInOrder = Array.from(toEl.children)
    .map((el) => (el as HTMLElement).getAttribute('data-raid-id'))
    .filter(Boolean)
    .map(Number)
    .filter((id) => Number.isInteger(id));
  if (raidIdsInOrder.length === 0) return;

  // Сразу обновляем дерево: убираем рейд из источника и вставляем в целевой список, чтобы не было дубликата до loadRaids()
  const movedRaid = findAndRemoveRaid(raids.value, movedRaidId);
  if (movedRaid) {
    const parent = parentId === null ? null : findRaidInTree(raids.value, parentId);
    const currentList = parentId === null ? raids.value : (parent?.children ?? []);
    const byId = new Map<number, RaidItem>();
    for (const r of currentList) byId.set(r.id, r);
    byId.set(movedRaid.id, movedRaid);
    const ordered = raidIdsInOrder.map((id) => byId.get(id)).filter((r): r is RaidItem => r != null);
    if (parentId === null) {
      raids.value = ordered;
    } else if (parent) {
      parent.children = ordered;
    }
    // Пересоздаём корневой Sortable, иначе он не обновляет DOM и элемент остаётся в двух местах
    raidSortableKey.value++;
  }

  dragSaving.value = true;
  try {
    await Promise.all(
      raidIdsInOrder.map((raidId, index) =>
        guildsApi.updateGuildRaid(guildId.value, raidId, {
          parent_id: parentId,
          sort_order: index,
        })
      )
    );
    await loadRaids();
  } catch {
    await loadRaids();
  } finally {
    dragSaving.value = false;
  }
}

function onRaidSortEnd(evt: { item: HTMLElement; to: HTMLElement; from?: HTMLElement }) {
  isDraggingRaid.value = false;
  handleRaidDrop(evt);
}

/** Для каждого рейда — сумма участников (своих + всех дочерних рекурсивно). */
const raidTotalMembers = computed(() => {
  const map = new Map<number, number>();
  function walk(items: RaidItem[]): void {
    for (const r of items) {
      if (r.children?.length) walk(r.children);
      const childSum = r.children?.length
        ? r.children.reduce((acc, c) => acc + (map.get(c.id) ?? 0), 0)
        : 0;
      map.set(r.id, (r.members_count ?? 0) + childSum);
    }
  }
  walk(raids.value);
  return map;
});

const raidSortableOptions = computed(() => ({
  group: 'raids',
  animation: 200,
  handle: '.raid-drag-handle',
  ghostClass: 'raid-drag-ghost',
  chosenClass: 'raid-drag-chosen',
  dragClass: 'raid-drag-drag',
  /** Ось списка — без неё swapThreshold может не применяться. */
  direction: 'vertical',
  /** Зона обмена от краёв: смена местами только когда курсор пересёк больше половины элемента (0.5 = 50%). */
  swapThreshold: 0.5,
  /** Зоны от краёв элемента, а не от центра — нужно перетащить дальше, чтобы сработала смена. */
  invertSwap: true,
  disabled: !canFormRaid.value || dragSaving.value,
}));

async function loadRaidPage() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;

  guild.value = null;
  raids.value = [];
  roster.value = [];
  selectedRaidId.value = null;
  selectedRaid.value = null;
  selectedRaidLoading.value = false;
  raidSheetOpen.value = false;
  loading.value = true;
  accessDenied.value = false;
  error.value = null;

  try {
    guild.value = await guildsApi.getGuildForSettings(guildId.value);
  } catch {
    guild.value = null;
    loading.value = false;
    return;
  }
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch {
    roster.value = [];
  }
  await loadRaids();
  loading.value = false;
}

watch(guildId, () => {
  loadRaidPage();
}, { immediate: true });
</script>

<template>
  <div class="container py-4 md:py-6 max-w-2xl mx-auto">

    <div class="flex justify-between items-center">
      <div class="text-xl font-semibold pb-4">Рейды · Группы · КП</div>
      <Button
        v-if="canFormRaid"
        type="button"
        @click="openCreate(null)"
      >
        Добавить рейд
      </Button>
    </div>

    <div class="relative">
      <p v-if="loading" class="text-sm text-muted-foreground">Загрузка…</p>
      <template v-else-if="accessDenied">
        <p class="text-sm text-muted-foreground">
          Раздел доступен только участникам гильдии.
        </p>
      </template>
      <template v-else-if="error">
        <p class="text-sm text-destructive">{{ error }}</p>
      </template>
      <template v-else-if="raids.length === 0 && !loading">
        <p class="text-sm text-muted-foreground">
          Рейдов пока нет.
          <template v-if="canFormRaid"> Нажмите «Добавить рейд», чтобы создать первый.</template>
        </p>
      </template>
      <div v-else>
        <ul class="raid-tree space-y-0" data-parent-id="">
          <Sortable
            :key="raidSortableKey"
            :list="raids"
            item-key="id"
            tag="div"
            class="contents"
            :options="raidSortableOptions"
            @start="isDraggingRaid = true"
            @end="onRaidSortEnd"
          >
            <template #item="{ element }">
              <RaidTreeItem
                :raid="element"
                :depth="0"
                :total-members="raidTotalMembers.get(element.id) ?? 0"
                :raid-total-members-map="Object.fromEntries(raidTotalMembers)"
                :can-edit="canFormRaid"
                :can-delete="canDeleteRaid"
                :selected-raid-id="selectedRaidId"
                :sortable-options="raidSortableOptions"
                :sortable-key="raidSortableKey"
                @add-child="(id) => openCreate(id)"
                @edit="openEdit"
                @delete="openDelete"
                @select="selectRaid"
                @sort-end="onRaidSortEnd"
              />
            </template>
            <template #footer>
              <li
                v-if="canFormRaid"
                class="list-none rounded-lg border-2 border-dashed text-center text-sm transition-all duration-150"
                :class="isDraggingRaid
                  ? 'border-muted-foreground/30 py-3 text-muted-foreground'
                  : 'min-h-0 border-transparent py-0 text-transparent'"
                data-drop-zone="root-end"
              >
                Перетащите сюда для переноса в конец списка (главный уровень)
              </li>
            </template>
          </Sortable>
        </ul>
      </div>

      <!-- Панель деталей выбранного рейда: наезжает справа, sticky при скролле страницы -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        leave-active-class="transition-all duration-150 ease-in"
        enter-from-class="translate-x-4 opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-4 opacity-0"
      >
        <div
          v-if="raidSheetOpen"
          class="pointer-events-none absolute inset-y-0 right-0 z-10 w-72 sm:w-80"
        >
          <aside
            class="pointer-events-auto sticky top-16 flex max-h-[calc(100vh-5rem)] flex-col rounded-lg border border-border bg-background shadow-xl"
          >
            <div class="flex items-center justify-between gap-2 border-b border-border p-3">
              <h3 class="min-w-0 flex-1 truncate text-base font-semibold" :title="selectedRaid?.name">
                {{ selectedRaid?.name ?? 'Рейд' }}
              </h3>
              <Button
                variant="ghost"
                size="sm"
                class="h-8 w-8 shrink-0 p-0"
                aria-label="Закрыть"
                @click="raidSheetOpen = false"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </Button>
            </div>

            <p v-if="selectedRaidLoading" class="p-3 text-sm text-muted-foreground">Загрузка…</p>

            <template v-else-if="selectedRaid">
              <div v-if="canFormRaid && !selectedRaidHasChildren" class="border-b border-border p-3">
                <Button type="button" class="w-full" @click="openFormRaidModal">
                  Сформировать рейд
                </Button>
              </div>

              <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3">
                <div v-if="selectedRaid.description" class="text-sm text-muted-foreground">
                  <p class="mb-1 text-[11px] font-medium uppercase tracking-wide">Описание</p>
                  <p class="whitespace-pre-wrap break-words">{{ selectedRaid.description }}</p>
                </div>

                <div class="flex min-h-0 flex-col">
                  <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-medium text-muted-foreground">
                      {{ selectedRaidHasChildren ? 'Все участники' : 'Участники рейда' }}
                    </p>
                    <span class="text-xs text-muted-foreground tabular-nums">
                      {{ unifiedMembers.length }}
                    </span>
                  </div>
                  <Input
                    v-if="unifiedMembers.length > 0"
                    v-model="memberSearchQuery"
                    type="text"
                    :placeholder="selectedRaidHasChildren ? 'Поиск по имени или рейду…' : 'Поиск по имени…'"
                    class="mb-2 h-8 text-sm"
                    autocomplete="off"
                  />
                  <ul
                    v-if="filteredUnifiedMembers.length > 0"
                    class="max-h-[400px] space-y-1.5 overflow-y-auto pr-1"
                  >
                    <li
                      v-for="m in filteredUnifiedMembers"
                      :key="`${m.leader_of ? 'L' : 'M'}-${m.raid_id}-${m.character_id}`"
                      class="flex min-w-0 items-center gap-2 rounded-md border px-2 py-1.5 text-sm"
                      :class="m.leader_of
                        ? 'border-primary/40 bg-primary/5'
                        : 'border-border/40 bg-muted/30'"
                    >
                      <Avatar
                        v-if="m.leader_of"
                        :alt="m.name"
                        :fallback="leaderInitials(m.name)"
                        class="h-7 w-7 shrink-0 rounded-full"
                      />
                      <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <div class="flex min-w-0 items-center gap-2">
                          <svg
                            v-if="m.leader_of"
                            xmlns="http://www.w3.org/2000/svg"
                            width="12"
                            height="12"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="shrink-0 text-primary"
                          >
                            <path d="M2 20l3-13 5 4 2-7 2 7 5-4 3 13z" />
                            <path d="M2 20h18" />
                          </svg>
                          <span class="min-w-0 flex-1 truncate font-medium" :title="m.name">{{ m.name }}</span>
                          <Badge v-if="m.role" variant="outline" class="shrink-0 text-xs">{{ m.role }}</Badge>
                        </div>
                        <span
                          v-if="m.leader_of"
                          class="min-w-0 truncate text-[11px] text-muted-foreground"
                          :title="`Лидер рейда «${m.leader_of}»`"
                        >
                          Лидер рейда «{{ m.leader_of }}»
                        </span>
                        <span
                          v-else-if="selectedRaidHasChildren"
                          class="min-w-0 truncate text-[11px] text-muted-foreground"
                          :title="m.raid_name"
                        >
                          {{ m.raid_name }}
                        </span>
                      </div>
                    </li>
                  </ul>
                  <p v-else-if="unifiedMembers.length === 0" class="text-sm text-muted-foreground">
                    Нет участников
                  </p>
                  <p v-else class="text-sm text-muted-foreground">Никого не найдено</p>
                </div>
              </div>
            </template>
          </aside>
        </div>
      </Transition>
    </div>

    <!-- Модальное окно создания/редактирования рейда -->
    <DialogRoot v-model:open="modalOpen">
      <ClientOnly>
      <DialogPortal>
        <DialogOverlay
          class="fixed inset-0 z-[3] bg-black/80 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 cursor-pointer"
        />
        <DialogContent
          class="fixed left-1/2 top-1/2 z-[4] w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 focus:outline-none max-h-[90vh] overflow-y-auto"
          :aria-describedby="undefined"
          @pointer-down-outside="closeRaidModal"
        >
          <div class="relative">
            <button
              type="button"
              class="absolute right-0 top-0 z-10 rounded-sm p-1 opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
              aria-label="Закрыть"
              @click="closeRaidModal()"
            >
              <span class="text-2xl leading-none">×</span>
            </button>
            <DialogTitle class="text-lg font-semibold pr-8">
            {{ formMode === 'create' ? 'Новый рейд' : 'Редактировать рейд' }}
          </DialogTitle>
          <form class="flex flex-col gap-4 pt-2" @submit.prevent="submitForm">
            <div class="space-y-2">
              <Label for="raid-name">Название <span class="text-destructive">*</span></Label>
              <Input
                id="raid-name"
                v-model="formName"
                type="text"
                placeholder="Например: Основная группа"
                maxlength="255"
                class="w-full"
              />
            </div>
            <div class="space-y-2">
              <Label for="raid-parent">Родительский рейд</Label>
              <SelectRoot
                v-model="formParentId"
                :disabled="parentOptions.length <= 1"
              >
                <SelectTrigger id="raid-parent" class="w-full">
                  <SelectValue placeholder="Корневой рейд" />
                </SelectTrigger>
                <SelectContent class="z-[100]">
                  <SelectItem
                    v-for="opt in parentOptions"
                    :key="opt.id"
                    :value="opt.id"
                    :disabled="(formRaidId != null && opt.id === String(formRaidId)) || (opt.members_count > 0) || (opt.depth >= 4)"
                  >
                    <span :style="{ paddingLeft: `${opt.depth * 12}px` }">
                      {{ opt.name }}
                    </span>
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>
            <div class="relative space-y-2">
              <Label for="raid-leader">Лидер</Label>
              <SelectRoot v-model="formLeaderId">
                <SelectTrigger id="raid-leader" class="w-full">
                  <SelectValue placeholder="Не назначен" />
                </SelectTrigger>
                <SelectContent class="z-[100]">
                  <div
                    class="sticky top-0 z-10 border-b bg-popover p-1.5"
                    @pointerdown.stop
                    @keydown.stop
                  >
                    <Input
                      v-model="leaderSearchQuery"
                      type="text"
                      placeholder="Поиск по имени..."
                      class="h-8 text-sm"
                      autocomplete="off"
                    />
                  </div>
                  <SelectItem :value="SELECT_NONE">
                    Не назначен
                  </SelectItem>
                  <SelectItem
                    v-for="m in leaderOptionsFiltered"
                    :key="m.character_id"
                    :value="String(m.character_id)"
                  >
                    {{ m.name }}
                  </SelectItem>
                </SelectContent>
              </SelectRoot>
            </div>
            <div class="space-y-2">
              <Label for="raid-desc">Описание</Label>
              <textarea
                id="raid-desc"
                v-model="formDescription"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Необязательно"
                rows="3"
              />
            </div>
            <p v-if="formError" class="text-sm text-destructive">{{ formError }}</p>
            <div class="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" :disabled="formSubmitting" @click="closeRaidModal">
                Отмена
              </Button>
              <Button type="submit" :disabled="formSubmitting">
                {{ formSubmitting ? 'Сохранение…' : (formMode === 'create' ? 'Создать' : 'Сохранить') }}
              </Button>
            </div>
          </form>
          </div>
        </DialogContent>
      </DialogPortal>
      </ClientOnly>
    </DialogRoot>

    <ConfirmDialog
      v-model:open="deleteDialogOpen"
      title="Удалить рейд?"
      description="Рейд и все подрейды будут удалены. Это действие нельзя отменить."
      confirm-label="Удалить"
      cancel-label="Отмена"
      :loading="deleteLoading"
      confirm-variant="destructive"
      @confirm="confirmDelete"
    />

    <FormRaidModal
      :open="formRaidModalOpen"
      :raid="selectedRaid"
      :roster="roster"
      :party-size="partySize"
      :guild-id="guildId"
      :saving="formRaidSaving"
      @close="closeFormRaidModal"
      @save="saveFormRaid"
    />
  </div>
</template>

<style scoped>
.raid-tree {
  min-height: 2px;
}
</style>

<style>
/* Стили перетаскивания (Sortable применяет классы к элементам вне компонента) */
.raid-drag-ghost {
  opacity: 0.5;
  background: hsl(var(--accent));
  border-radius: 0.5rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.raid-drag-chosen {
  opacity: 0.9;
}
.raid-drag-drag {
  opacity: 1;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  border-radius: 0.5rem;
  background-color: white;
}
</style>
