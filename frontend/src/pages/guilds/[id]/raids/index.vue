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
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
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

/** Участники выбранного рейда. */
const selectedRaidMembers = computed((): RaidMemberItem[] => {
  return selectedRaid.value?.members ?? [];
});

async function selectRaid(raid: RaidItem) {
  if (selectedRaidId.value === raid.id) return;
  selectedRaidId.value = raid.id;
  selectedRaidLoading.value = true;
  selectedRaid.value = null;
  try {
    selectedRaid.value = await guildsApi.getGuildRaid(guildId.value, raid.id);
  } catch {
    selectedRaid.value = null;
  } finally {
    selectedRaidLoading.value = false;
  }
}

function clearSelectedRaid() {
  selectedRaidId.value = null;
  selectedRaid.value = null;
}

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

    <div class="">
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
      <div v-else class="flex flex-col gap-4 md:flex-row md:items-stretch">
        <div class="min-w-0 flex-1">
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
        <aside
          v-if="selectedRaidId != null"
          class="w-full shrink-0 rounded-lg border border-border bg-muted/30 md:w-80"
        >
          <div class="flex flex-col gap-3 p-4">
            <div class="flex items-center justify-between gap-2 border-b border-border pb-2">
              <h3 class="font-semibold truncate" :title="selectedRaid?.name">
                {{ selectedRaid?.name ?? 'Рейд' }}
              </h3>
              <Button variant="ghost" size="sm" class="h-8 w-8 shrink-0 p-0" aria-label="Закрыть" @click="clearSelectedRaid">
                ×
              </Button>
            </div>
            <p v-if="selectedRaidLoading" class="text-sm text-muted-foreground">Загрузка…</p>
            <template v-else>
              <p class="text-sm font-medium text-muted-foreground">Участники рейда</p>
              <ul v-if="selectedRaidMembers.length > 0" class="space-y-1.5">
                <li
                  v-for="m in selectedRaidMembers"
                  :key="m.character_id"
                  class="flex items-center gap-2 rounded-md bg-background px-2 py-1.5 text-sm"
                >
                  <span class="min-w-0 truncate">{{ m.name }}</span>
                  <Badge v-if="m.role" variant="outline" class="shrink-0 text-xs">{{ m.role }}</Badge>
                </li>
              </ul>
              <p v-else class="text-sm text-muted-foreground">Нет участников</p>
              <Button
                v-if="canFormRaid && !selectedRaidHasChildren"
                type="button"
                class="mt-1"
                @click="openFormRaidModal"
              >
                Сформировать рейд
              </Button>
              <p v-else-if="canFormRaid && selectedRaidHasChildren" class="mt-1 text-xs text-muted-foreground">
                Рейд с дочерними рейдами не может иметь участников.
              </p>
            </template>
          </div>
        </aside>
      </div>
    </div>

    <!-- Модальное окно создания/редактирования рейда -->
    <DialogRoot v-model:open="modalOpen">
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
