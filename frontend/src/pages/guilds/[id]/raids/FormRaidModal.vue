<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Button, Input } from '@/shared/ui';
import type {
  RaidItem,
  RaidMemberItem,
  GuildRosterMember,
  RaidCompositionMemberPayload,
} from '@/shared/api/guildsApi';

const props = defineProps<{
  open: boolean;
  raid: RaidItem | null;
  roster: GuildRosterMember[];
  partySize: number;
  guildId: number;
  saving?: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'save', members: RaidCompositionMemberPayload[]): void;
}>();

/** Участник с локальным slot_index (в пуле или в ячейке). */
interface MemberSlot {
  character_id: number;
  name: string;
  slot_index: number | null;
}

const searchQuery = ref('');
const members = ref<MemberSlot[]>([]);
const draggedCharacterId = ref<number | null>(null);
const dragOverSlotIndex = ref<number | null>(null);
const dragOverRoster = ref(false);
const rosterDropZoneRef = ref<HTMLElement | null>(null);

/** Сетка 100×100 ячеек. */
const GRID_COLUMNS = 50;
const GRID_ROWS = 50;
const totalSlots = computed(() => GRID_COLUMNS * GRID_ROWS);

/** Блок — группа из partySize строк (одна пати). В каждом блоке отступ после последней строки. */
const partyColumns = computed(() => {
  const partySize = Math.max(1, props.partySize);
  const list: { partyIndex: number; blocks: number[][] }[] = [];
  for (let c = 0; c < GRID_COLUMNS; c++) {
    const blocks: number[][] = [];
    for (let r = 0; r < GRID_ROWS; r += partySize) {
      const block: number[] = [];
      for (let i = 0; i < partySize && r + i < GRID_ROWS; i++) {
        block.push(c * GRID_ROWS + r + i);
      }
      blocks.push(block);
    }
    list.push({ partyIndex: c, blocks });
  }
  return list;
});

watch(
  () => [props.open, props.raid],
  () => {
    if (props.open && props.raid?.members) {
      members.value = props.raid.members.map((m: RaidMemberItem) => ({
        character_id: m.character_id,
        name: m.name,
        slot_index: m.slot_index ?? null,
      }));
    } else {
      members.value = [];
    }
    searchQuery.value = '';
    draggedCharacterId.value = null;
    dragOverSlotIndex.value = null;
    dragOverRoster.value = false;
  },
  { immediate: true }
);

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  document.body.style.overflow = '';
});

/** Персонажи гильдии, которые не в рейде. */
const rosterNotInRaid = computed(() =>
  props.roster.filter((r) => !members.value.some((m) => m.character_id === r.character_id))
);

/** Состав гильдии (не в рейде), отфильтрованный по поиску, по алфавиту. */
const rosterFiltered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const list = q
    ? rosterNotInRaid.value.filter((r) => r.name.toLowerCase().includes(q))
    : rosterNotInRaid.value;
  return [...list].sort((a, b) => a.name.localeCompare(b.name, 'ru'));
});

/** По slot_index получить участника. */
const memberBySlotIndex = computed(() => {
  const map = new Map<number, MemberSlot>();
  for (const m of members.value) {
    if (m.slot_index !== null) map.set(m.slot_index, m);
  }
  return map;
});

function getMemberByCharacterId(characterId: number): MemberSlot | undefined {
  return members.value.find((m) => m.character_id === characterId);
}

function onDragStart(e: DragEvent, characterId: number) {
  draggedCharacterId.value = characterId;
  e.dataTransfer!.effectAllowed = 'move';
  e.dataTransfer!.setData('text/plain', String(characterId));
  e.dataTransfer!.setData('application/json', JSON.stringify({ character_id: characterId }));
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}

function onDragEnd() {
  draggedCharacterId.value = null;
  dragOverSlotIndex.value = null;
  dragOverRoster.value = false;
}

function onDragOverSlot(e: DragEvent, slotIndex: number) {
  e.preventDefault();
  e.dataTransfer!.dropEffect = 'move';
  dragOverSlotIndex.value = slotIndex;
}

function onDragLeaveSlot() {
  dragOverSlotIndex.value = null;
}

function onDragOverRoster(e: DragEvent) {
  e.preventDefault();
  e.dataTransfer!.dropEffect = 'move';
  dragOverRoster.value = true;
}

function onDragLeaveRoster(e: DragEvent) {
  const el = rosterDropZoneRef.value;
  const related = e.relatedTarget as Node | null;
  if (el && related && el.contains(related)) return;
  dragOverRoster.value = false;
}

function onDropRoster(e: DragEvent) {
  e.preventDefault();
  dragOverRoster.value = false;
  const characterId = draggedCharacterId.value ?? (e.dataTransfer?.getData('text/plain') ? Number(e.dataTransfer.getData('text/plain')) : null);
  if (characterId == null || !Number.isInteger(characterId)) return;
  if (getMemberByCharacterId(characterId)) removeFromRaid(characterId);
}

function buildPayload(): RaidCompositionMemberPayload[] {
  return members.value.map((m) => ({
    character_id: m.character_id,
    slot_index: m.slot_index,
  }));
}

function saveNow() {
  emit('save', buildPayload());
}

function onDropSlot(e: DragEvent, slotIndex: number) {
  e.preventDefault();
  dragOverSlotIndex.value = null;
  const characterId = draggedCharacterId.value ?? (e.dataTransfer?.getData('text/plain') ? Number(e.dataTransfer.getData('text/plain')) : null);
  if (characterId == null || !Number.isInteger(characterId)) return;
  let member = getMemberByCharacterId(characterId);
  if (!member) {
    const rosterMember = props.roster.find((r) => r.character_id === characterId);
    if (rosterMember) {
      member = { character_id: rosterMember.character_id, name: rosterMember.name, slot_index: null };
      members.value.push(member);
    } else return;
  }
  const previousSlot = member.slot_index;
  const previousInSlot = members.value.find((m) => m.slot_index === slotIndex);
  member.slot_index = slotIndex;
  if (previousInSlot && previousInSlot.character_id !== characterId) {
    previousInSlot.slot_index = previousSlot;
  }
  saveNow();
}

function removeFromRaid(characterId: number) {
  const idx = members.value.findIndex((m) => m.character_id === characterId);
  if (idx !== -1) {
    members.value.splice(idx, 1);
    saveNow();
  }
}

function close() {
  emit('close');
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[50] flex flex-col bg-background"
      role="dialog"
      aria-modal="true"
      aria-labelledby="form-raid-title"
    >
      <header class="flex shrink-0 items-center justify-between gap-2 border-b px-4 py-3">
        <div class="min-w-0">
          <h1 id="form-raid-title" class="text-lg font-semibold truncate">
            {{ raid?.name ?? 'Рейд' }}
          </h1>
          <p class="text-xs text-muted-foreground">В рейде: {{ members.length }} участников</p>
        </div>
        <Button variant="ghost" size="sm" class="h-9 w-9 shrink-0 p-0" aria-label="Закрыть" @click="close">
          ×
        </Button>
      </header>

      <div class="flex min-h-0 flex-1 overflow-hidden">
        <!-- Левая панель: состав гильдии -->
        <aside class="flex w-64 shrink-0 flex-col border-r overflow-hidden">
          <div class="shrink-0 border-b p-2">
            <div class="relative">
              <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="11" cy="11" r="8"/>
                  <path d="m21 21-4.3-4.3"/>
                </svg>
              </span>
              <Input
                v-model="searchQuery"
                type="text"
                placeholder="Поиск"
                class="h-9 pl-8"
                autocomplete="off"
              />
            </div>
          </div>
          <div
            ref="rosterDropZoneRef"
            class="flex flex-1 flex-col overflow-y-auto p-2 transition-colors"
            :class="{ 'ring-2 ring-primary/40 rounded-md bg-primary/5': dragOverRoster }"
            @dragover="onDragOverRoster"
            @dragleave="onDragLeaveRoster"
            @drop="onDropRoster"
          >
            <p class="mb-2 text-xs font-medium text-muted-foreground">
              Состав гильдии
              <span v-if="dragOverRoster" class="text-primary"> — отпустите, чтобы убрать из рейда</span>
            </p>
            <div class="space-y-1.5">
              <div
                v-for="r in rosterFiltered"
                :key="r.character_id"
                draggable="true"
                class="min-w-0 rounded-lg border bg-card px-2.5 py-2 text-sm cursor-grab active:cursor-grabbing hover:bg-muted/50"
                @dragstart="onDragStart($event, r.character_id)"
                @dragend="onDragEnd"
              >
                <span class="block min-w-0 truncate" :title="r.name">{{ r.name }}</span>
              </div>
            </div>
          </div>
        </aside>

        <!-- Правая часть: сетка ячеек 100×100 -->
        <div class="flex-1 overflow-auto p-4">
          <p class="mb-3 text-sm text-muted-foreground">
            Перетащите участников в ячейки. Сетка {{ GRID_COLUMNS }} x {{ GRID_ROWS }}.
          </p>
          <div class="flex flex-nowrap gap-x-1">
            <div
              v-for="party in partyColumns"
              :key="party.partyIndex"
              class="flex flex-col rounded-md bg-muted/30 p-1.5 gap-5"
            >
              <div
                v-for="(block, blockIdx) in party.blocks"
                :key="blockIdx"
                class="mb-2 flex flex-col gap-1.5 last:mb-0"
              >
                <p class="text-center text-xs font-medium text-muted-foreground">
                  Пати {{ blockIdx * partyColumns.length + party.partyIndex + 1 }}
                </p>
                <div
                  v-for="slotIndex in block"
                  :key="slotIndex"
                  :data-slot-index="slotIndex"
                  class="flex h-8 w-24 min-w-[96px] items-center justify-center rounded border border-border bg-background/80 py-2 text-center text-sm transition-colors"
                  :class="{
                    'border-primary bg-primary/10 ring-1 ring-primary/30': dragOverSlotIndex === slotIndex,
                  }"
                  @dragover="onDragOverSlot($event, slotIndex)"
                  @dragleave="onDragLeaveSlot"
                  @drop="onDropSlot($event, slotIndex)"
                >
                <template v-if="memberBySlotIndex.get(slotIndex)">
                  <div class="flex w-full items-center justify-center gap-0.5">
                    <span
                      draggable="true"
                      class="min-w-0 flex-1 cursor-grab truncate px-1 font-medium active:cursor-grabbing hover:opacity-90"
                      :title="memberBySlotIndex.get(slotIndex)!.name"
                      @dragstart="onDragStart($event, memberBySlotIndex.get(slotIndex)!.character_id)"
                      @dragend="onDragEnd"
                    >
                      {{ memberBySlotIndex.get(slotIndex)!.name }}
                    </span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-5 w-5 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                      aria-label="Убрать из рейда"
                      @click.stop="removeFromRaid(memberBySlotIndex.get(slotIndex)!.character_id)"
                    >
                      ×
                    </Button>
                  </div>
                </template>
                <span v-else class="text-muted-foreground/50">—</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
