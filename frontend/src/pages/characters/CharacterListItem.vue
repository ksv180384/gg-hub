<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { computed } from 'vue';
import { Avatar, Badge, Button, Tooltip } from '@/shared/ui';
import type { Character } from '@/shared/api/charactersApi';
import CharacterClassBadge from './CharacterClassBadge.vue';
import {
  rosterTagBadgeClass,
  sliceRosterTagRowsForDisplay,
  sortRosterTagRows,
  type RosterTagRow,
} from '@/shared/lib/rosterTagDisplay';

const props = defineProps<{
  character: Character;
  deleting?: boolean;
}>();

const emit = defineEmits<{ (e: 'edit'): void; (e: 'delete'): void }>();

const tagRows = computed((): RosterTagRow[] => {
  const tags = props.character.tags ?? [];
  return sortRosterTagRows(tags.map((t) => ({
    tag: t,
    source: t.used_by_guild_id != null ? 'guild' : 'personal',
  })));
});

const tagsUi = computed(() => sliceRosterTagRowsForDisplay(tagRows.value));
</script>

<template>
  <li
    class="rounded-xl border bg-card px-3 py-3 transition-colors hover:bg-muted/50 sm:px-4"
  >
    <div class="flex min-w-0 items-center gap-3 sm:gap-4">
      <Avatar
        :src="character.avatar_url ?? undefined"
        :alt="character.name"
        :fallback="character.name.slice(0, 2).toUpperCase()"
        class="h-20 w-20 shrink-0 sm:h-14 sm:w-14"
      />

      <div class="min-w-0 flex-1">
        <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-6">
          <div class="min-w-0">
            <div class="flex min-w-0 items-center gap-2">
              <p class="min-w-0 truncate text-base font-semibold leading-tight sm:text-lg">
                {{ character.name }}
              </p>
              <Tooltip v-if="character.is_main" content="Основной">
                <span class="inline-flex shrink-0 text-amber-500" aria-label="Основной">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
                    <path d="M2 17l2-7 4 3 4-6 4 6 4-3 2 7H2zm0 2h20v2H2v-2z" />
                  </svg>
                </span>
              </Tooltip>
            </div>

            <p class="mt-0.5 text-sm text-muted-foreground">
              <span v-if="character.localization?.name" :title="'Локализация'">{{ character.localization.name }}</span>
              <template v-if="character.localization?.name && character.server?.name"> · </template>
              <span v-if="character.server?.name" :title="'Сервер'">{{ character.server.name }}</span>
              <template v-if="!character.localization?.name && !character.server?.name">—</template>
            </p>

            <p v-if="character.guild" class="mt-0.5 text-sm text-muted-foreground">
              <span>Гильдия: </span>
              <RouterLink
                :to="{ name: 'guild-info', params: { id: character.guild.id } }"
                class="text-primary hover:underline"
              >
                {{ character.guild.name }}
              </RouterLink>
            </p>

            <div v-if="character.game_classes?.length" class="mt-2 flex flex-wrap items-center gap-1.5 sm:hidden">
              <CharacterClassBadge
                v-for="gc in character.game_classes"
                :key="gc.id"
                :game-class="gc"
              />
            </div>

            <div v-if="tagsUi.visible.length" class="mt-1 flex flex-wrap items-center gap-1.5 sm:hidden">
              <Badge
                v-for="row in tagsUi.visible"
                :key="row.source + '-' + row.tag.id"
                variant="outline"
                :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs font-normal']"
              >
                {{ row.tag.name }}
              </Badge>
              <span
                v-if="tagsUi.moreCount > 0"
                class="text-xs text-muted-foreground"
                :title="`Ещё ${tagsUi.moreCount} тегов`"
              >
                +{{ tagsUi.moreCount }}
              </span>
            </div>
          </div>

          <div class="hidden shrink-0 self-center sm:block">
            <div v-if="character.game_classes?.length" class="flex flex-wrap items-center gap-1.5">
              <CharacterClassBadge
                v-for="gc in character.game_classes"
                :key="gc.id"
                :game-class="gc"
              />
            </div>

            <div v-if="tagsUi.visible.length" class="mt-1 flex flex-wrap items-center gap-1.5">
              <Badge
                v-for="row in tagsUi.visible"
                :key="row.source + '-' + row.tag.id"
                variant="outline"
                :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs font-normal']"
              >
                {{ row.tag.name }}
              </Badge>
              <span
                v-if="tagsUi.moreCount > 0"
                class="text-xs text-muted-foreground"
                :title="`Ещё ${tagsUi.moreCount} тегов`"
              >
                +{{ tagsUi.moreCount }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex shrink-0 flex-col items-center gap-3 sm:flex-row sm:gap-1.5">
        <Tooltip content="Редактировать">
          <Button
            type="button"
            size="icon"
            variant="ghost"
            class="h-10 w-10 shrink-0 min-h-10 min-w-10 touch-manipulation"
            aria-label="Редактировать"
            @click="emit('edit')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
              <path d="m15 5 4 4"/>
            </svg>
          </Button>
        </Tooltip>
        <Tooltip content="Удалить персонажа">
          <Button
            type="button"
            size="icon"
            variant="ghost"
            class="h-10 w-10 shrink-0 min-h-10 min-w-10 touch-manipulation text-destructive hover:text-destructive hover:bg-destructive/10"
            aria-label="Удалить персонажа"
            :disabled="deleting"
            @click="emit('delete')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden>
              <path d="M3 6h18"/>
              <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>
              <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
              <line x1="10" x2="10" y1="11" y2="17"/>
              <line x1="14" x2="14" y1="11" y2="17"/>
            </svg>
          </Button>
        </Tooltip>
      </div>
    </div>
  </li>
</template>
