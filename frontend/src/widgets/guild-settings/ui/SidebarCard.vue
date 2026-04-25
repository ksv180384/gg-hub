<script setup lang="ts">
import {
  Badge,
  Button,
  Label,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/shared/ui';
import type { Tag } from '@/shared/api/tagsApi';
import type { Guild, GuildRosterMember } from '@/shared/api/guildsApi';

defineProps<{
  guild: Guild;
  isOwner: boolean;
  canEditGuildData: boolean;
  saving: boolean;

  // logo
  acceptImages: string;
  fileInputRef: HTMLInputElement | null;
  dragOver: boolean;
  logoDisplayUrl: string | null;

  // left meta
  selectedTagIds: number[];
  allTags: Tag[];

  // leader change
  canChangeGuildLeader: boolean;
  leaderRosterMembers: GuildRosterMember[];
  selectedLeaderCharacterId: string;
  fieldErrors: Record<string, string>;

  // leave
  leaving: boolean;
  leaveError: string | null;
}>();

const emit = defineEmits<{
  // logo
  (e: 'logoChange', ev: Event): void;
  (e: 'openFilePicker'): void;
  (e: 'logoDragOver', ev: DragEvent): void;
  (e: 'logoDragLeave'): void;
  (e: 'logoDrop', ev: DragEvent): void;
  (e: 'removeLogo'): void;

  // leader
  (e: 'update:selectedLeaderCharacterId', value: string): void;

  // leave
  (e: 'openLeave'): void;
}>();

function selectedTags(allTags: Tag[], selectedIds: number[]) {
  return allTags.filter((t) => selectedIds.includes(t.id));
}
</script>

<template>
  <div class="flex w-full shrink-0 flex-col items-center order-1 md:order-1 md:w-[290px]">
    <h1 class="mb-3 w-full text-center text-xl font-bold md:text-2xl">{{ guild.name }}</h1>

    <input
      ref="fileInputRef"
      type="file"
      :accept="acceptImages"
      class="sr-only"
      @change="emit('logoChange', $event)"
    />

    <div
      v-if="canEditGuildData"
      role="button"
      tabindex="0"
      aria-label="Загрузить логотип гильдии"
      class="relative flex h-[290px] w-full max-w-[290px] cursor-pointer shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      :class="
        dragOver
          ? 'border-primary bg-primary/5'
          : 'border-muted-foreground/30 bg-muted/30 hover:border-muted-foreground/50 hover:bg-muted/50'
      "
      @click="emit('openFilePicker')"
      @keydown.enter.prevent="emit('openFilePicker')"
      @keydown.space.prevent="emit('openFilePicker')"
      @dragover.prevent="emit('logoDragOver', $event)"
      @dragleave="emit('logoDragLeave')"
      @drop.prevent="emit('logoDrop', $event)"
    >
      <template v-if="logoDisplayUrl">
        <img :src="logoDisplayUrl" alt="Логотип гильдии" class="absolute inset-0 h-full w-full object-cover" />
        <div class="absolute inset-0 flex items-end justify-center rounded-lg bg-black/40 p-2 opacity-0 transition-opacity hover:opacity-100">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            class="text-xs"
            :disabled="saving || !isOwner"
            @click.stop="emit('removeLogo')"
          >
            Удалить
          </Button>
        </div>
      </template>
      <template v-else>
        <span v-if="saving" class="text-sm text-muted-foreground">Загрузка…</span>
        <span v-else class="px-3 text-center text-sm text-muted-foreground">
          Перетащите изображение сюда или нажмите для выбора
        </span>
      </template>
    </div>

    <div
      v-else
      class="relative flex h-[290px] w-full max-w-[290px] shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg bg-muted/20"
    >
      <img v-if="logoDisplayUrl" :src="logoDisplayUrl" alt="Логотип гильдии" class="h-full w-full object-cover" />
      <span v-else class="text-sm text-muted-foreground">Нет логотипа</span>
    </div>

    <div class="mt-3 flex w-full max-w-[290px] flex-col items-center gap-3 text-center text-sm">
      <div class="font-medium text-foreground">
        Лидер: {{ guild.leader?.name ?? '—' }}
      </div>

      <div v-if="selectedTagIds.length" class="flex w-full flex-wrap justify-center gap-2 md:justify-start">
        <Badge v-for="tag in selectedTags(allTags, selectedTagIds)" :key="tag.id" variant="outline">
          {{ tag.name }}
        </Badge>
      </div>

      <div class="text-muted-foreground">
        Участников: {{ guild.members_count ?? 0 }}
      </div>

      <div
        v-if="canChangeGuildLeader && leaderRosterMembers.length"
        class="w-full space-y-1.5 text-left"
      >
        <Label class="text-xs text-muted-foreground">Сменить лидера</Label>
        <SelectRoot
          :model-value="selectedLeaderCharacterId"
          @update:model-value="emit('update:selectedLeaderCharacterId', String($event))"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Участник гильдии" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="m in leaderRosterMembers"
              :key="m.character_id"
              :value="String(m.character_id)"
            >
              {{ m.name }}
            </SelectItem>
          </SelectContent>
        </SelectRoot>
        <p class="text-xs text-muted-foreground">
          Только участники состава. Сохраните изменения во вкладке «Настройки» справа.
        </p>
        <p v-if="fieldErrors.leader_character_id" class="text-xs text-destructive">
          {{ fieldErrors.leader_character_id }}
        </p>
      </div>

      <div v-if="!canChangeGuildLeader" class="mt-0">
        <Button variant="destructive" size="sm" :disabled="leaving" @click="emit('openLeave')">
          Покинуть гильдию
        </Button>
        <p v-if="leaveError" class="mt-1 text-xs text-destructive">{{ leaveError }}</p>
      </div>
    </div>
  </div>
</template>

