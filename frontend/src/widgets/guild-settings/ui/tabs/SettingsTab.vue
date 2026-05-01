<script setup lang="ts">
import {
  Button,
  Card,
  CardContent,
  Input,
  Label,
  Badge,
  TagAddCombobox,
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  Tooltip,
} from '@/shared/ui';
import type { Tag } from '@/shared/api/tagsApi';
import type { GuildRosterMember } from '@/shared/api/guildsApi';

defineProps<{
  // owner/rights
  isOwner: boolean;
  canEditGuildData: boolean;
  canEditGuildTags: boolean;
  canOpenGuildTagPicker: boolean;
  canCreateGuildTag: boolean;
  canDeleteGuildTag: (tag: Tag) => boolean;

  // leader change
  canChangeGuildLeader: boolean;
  leaderRosterMembers: GuildRosterMember[];
  selectedLeaderCharacterId: string;

  // form values
  name: string;
  selectedLocalizationId: string;
  selectedServerId: string;
  canChangeLocalizationServer: boolean;
  availableLocalizations: { id: number; name: string }[];
  servers: { id: number; name: string }[];

  // tags
  allTags: Tag[];
  selectedTagIds: number[];
  guildId: number;

  // misc
  saving: boolean;
  fieldErrors: Record<string, string>;
  localizationServerInfo: string;
}>();

const emit = defineEmits<{
  (e: 'update:name', value: string): void;
  (e: 'update:selectedLocalizationId', value: string): void;
  (e: 'update:selectedServerId', value: string): void;
  (e: 'update:selectedLeaderCharacterId', value: string): void;
  (e: 'save'): void;
  (e: 'toggleTag', tagId: number): void;
  (e: 'update:allTags', value: Tag[]): void;
  (e: 'update:selectedTagIds', value: number[]): void;
  (e: 'deleteTag', tag: Tag): void;
}>();

function selectedTags(allTags: Tag[], selectedIds: number[]) {
  return allTags.filter((t) => selectedIds.includes(t.id));
}
</script>

<template>
  <Card v-show="true" class="mb-6 border-0 p-0 shadow-none">
    <CardContent class="space-y-6 px-2">
      <p v-if="!isOwner && !canChangeGuildLeader" class="text-sm text-muted-foreground">
        Редактировать настройки может только лидер гильдии или участник с соответствующим правом. Вы можете просматривать информацию.
      </p>
      <p v-else-if="!isOwner && canChangeGuildLeader" class="text-sm text-muted-foreground">
        Вы можете сменить лидера гильдии (слева, под числом участников) и нажать «Сохранить настройки».
      </p>

      <div class="space-y-2">
        <Label for="settings-name">Название гильдии *</Label>
        <Input
          id="settings-name"
          :model-value="name"
          :disabled="!isOwner"
          @update:model-value="emit('update:name', $event)"
        />
        <p v-if="fieldErrors.name" class="text-sm text-destructive">{{ fieldErrors.name }}</p>
      </div>

      <div class="space-y-2">
        <div class="flex items-center gap-1.5">
          <Label>Локализация *</Label>
          <Tooltip :content="localizationServerInfo" side="top">
            <button
              type="button"
              aria-label="Подсказка: когда можно менять локализацию"
              class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 16v-4" />
                <path d="M12 8h.01" />
              </svg>
            </button>
          </Tooltip>
        </div>
        <SelectRoot
          :model-value="selectedLocalizationId"
          :disabled="!isOwner || !canChangeLocalizationServer || !availableLocalizations.length"
          @update:model-value="emit('update:selectedLocalizationId', String($event))"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Локализация" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="loc in availableLocalizations" :key="loc.id" :value="String(loc.id)">
              {{ loc.name }}
            </SelectItem>
          </SelectContent>
        </SelectRoot>
        <p v-if="fieldErrors.localization_id" class="text-sm text-destructive">
          {{ fieldErrors.localization_id }}
        </p>
      </div>

      <div class="space-y-2">
        <div class="flex items-center gap-1.5">
          <Label>Сервер *</Label>
          <Tooltip :content="localizationServerInfo" side="top">
            <button
              type="button"
              aria-label="Подсказка: когда можно менять сервер"
              class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 16v-4" />
                <path d="M12 8h.01" />
              </svg>
            </button>
          </Tooltip>
        </div>
        <SelectRoot
          :model-value="selectedServerId"
          :disabled="!isOwner || !canChangeLocalizationServer || !servers.length"
          @update:model-value="emit('update:selectedServerId', String($event))"
        >
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Сервер" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="srv in servers" :key="srv.id" :value="String(srv.id)">
              {{ srv.name }}
            </SelectItem>
          </SelectContent>
        </SelectRoot>
        <p v-if="fieldErrors.server_id" class="text-sm text-destructive">
          {{ fieldErrors.server_id }}
        </p>
      </div>

      <div class="space-y-3">
        <Label>Теги гильдии</Label>
        <p class="text-xs text-muted-foreground">
          Выберите теги для гильдии или добавьте новый — он станет доступен всем.
        </p>

        <div v-if="selectedTagIds.length" class="flex flex-wrap gap-2">
          <template v-for="tag in selectedTags(allTags, selectedTagIds)" :key="tag.id">
            <Badge v-if="!canEditGuildTags" variant="outline">
              {{ tag.name }}
            </Badge>
            <Badge
              v-else
              variant="outline"
              class="max-w-full gap-0.5 py-0 pl-2 pr-0.5"
            >
              <span class="min-w-0 truncate">{{ tag.name }}</span>
              <button
                type="button"
                class="inline-flex shrink-0 rounded-sm p-0.5 text-muted-foreground outline-none hover:bg-destructive/15 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring"
                :title="`Убрать тег «${tag.name}»`"
                :aria-label="`Убрать тег «${tag.name}»`"
                @click.stop.prevent="emit('toggleTag', tag.id)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M18 6 6 18" />
                  <path d="m6 6 12 12" />
                </svg>
              </button>
            </Badge>
          </template>
        </div>

        <TagAddCombobox
          :all-tags="allTags"
          :selected-tag-ids="selectedTagIds"
          input-id="tag-select"
          :disabled="!canOpenGuildTagPicker"
          :allow-create-tag="canCreateGuildTag"
          :tag-create-guild-id="guildId"
          :can-delete-tag="canDeleteGuildTag"
          @update:all-tags="emit('update:allTags', $event)"
          @update:selected-tag-ids="emit('update:selectedTagIds', $event)"
          @delete-tag="emit('deleteTag', $event)"
        />
      </div>

      <Button :disabled="saving || (!isOwner && !canChangeGuildLeader)" @click="emit('save')">
        {{ saving ? 'Сохранение…' : 'Сохранить настройки' }}
      </Button>
    </CardContent>
  </Card>
</template>

