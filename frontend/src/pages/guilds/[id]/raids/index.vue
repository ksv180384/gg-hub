<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
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
  ConfirmDialog,
  Badge,
} from '@/shared/ui';
import {
  guildsApi,
  type Guild,
  type RaidItem,
  type GuildRosterMember,
  type CreateRaidPayload,
  type UpdateRaidPayload,
} from '@/shared/api/guildsApi';
import RaidTreeItem from './RaidTreeItem.vue';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const raids = ref<RaidItem[]>([]);
const roster = ref<GuildRosterMember[]>([]);
const loading = ref(true);
const accessDenied = ref(false);
const error = ref<string | null>(null);

const canFormRaid = computed(
  () => guild.value?.my_permission_slugs?.includes('formirovat-reidy') ?? false
);
const canDeleteRaid = computed(
  () => guild.value?.my_permission_slugs?.includes('udaliat-reidy') ?? false
);

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
function flattenRaids(items: RaidItem[], excludeId?: number): { id: number; name: string; depth: number }[] {
  const out: { id: number; name: string; depth: number }[] = [];
  function walk(list: RaidItem[], depth: number) {
    for (const r of list) {
      if (r.id === excludeId) continue;
      out.push({ id: r.id, name: r.name, depth });
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
  return [{ id: SELECT_NONE, name: '— Без родителя (корневой рейд) —', depth: 0 }, ...flat.map((r) => ({ id: String(r.id), name: r.name, depth: r.depth }))];
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

async function handleRaidDrop(evt: { item: HTMLElement; to: HTMLElement }) {
  const movedRaidId = Number(evt.item.getAttribute('data-raid-id'));
  if (!Number.isInteger(movedRaidId)) return;
  const toList = evt.to as HTMLElement;
  const parentIdAttr = toList.getAttribute('data-parent-id');
  const parentId = parentIdAttr === null || parentIdAttr === '' ? null : Number(parentIdAttr);
  const descendantIds = getDescendantIds(raids.value, movedRaidId);
  if (parentId !== null && descendantIds.has(parentId)) {
    await loadRaids();
    return;
  }
  // Новый порядок по DOM после дропа — обновляем sort_order у всех в списке
  const raidIdsInOrder = Array.from(toList.children)
    .map((el) => el.getAttribute('data-raid-id'))
    .filter(Boolean)
    .map(Number)
    .filter((id) => Number.isInteger(id));
  if (raidIdsInOrder.length === 0) return;
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

const raidSortableConfig = computed(() => ({
  disabled: !canFormRaid.value || dragSaving.value,
  options: {
    group: 'raids',
    animation: 200,
    handle: '.raid-drag-handle',
    ghostClass: 'raid-drag-ghost',
    chosenClass: 'raid-drag-chosen',
    dragClass: 'raid-drag-drag',
    onEnd: handleRaidDrop,
  },
}));

onMounted(async () => {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
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
});
</script>

<template>
  <div class="container py-4 md:py-6">
    <Card>
      <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-4">
        <CardTitle>Рейды · Группы · КП</CardTitle>
        <Button
          v-if="canFormRaid"
          type="button"
          @click="openCreate(null)"
        >
          Добавить рейд
        </Button>
      </CardHeader>
      <CardContent>
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
        <ul
          v-else
          class="raid-tree space-y-0"
          data-parent-id=""
          v-sortable="raidSortableConfig"
        >
          <RaidTreeItem
            v-for="raid in raids"
            :key="raid.id"
            :raid="raid"
            :can-edit="canFormRaid"
            :can-delete="canDeleteRaid"
            :sortable-config="raidSortableConfig"
            @add-child="(id) => openCreate(id)"
            @edit="openEdit"
            @delete="openDelete"
          />
        </ul>
      </CardContent>
    </Card>

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
                    :disabled="formRaidId != null && opt.id === String(formRaidId)"
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
}
</style>
