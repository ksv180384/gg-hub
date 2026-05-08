<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Avatar, Badge, Tooltip } from '@/shared/ui';
import type { Character } from '@/shared/api/charactersApi';
import { CharacterClassBadge } from '@/entities/character';
import {
  rosterTagBadgeClass,
  sliceRosterTagRowsForDisplay,
  sortRosterTagRows,
  type RosterTagRow,
} from '@/shared/lib/rosterTagDisplay';

const props = defineProps<{
  character: Character;
  clickable?: boolean;
}>();

defineEmits<{ (e: 'click'): void }>();

const tagRows = computed((): RosterTagRow[] => {
  const tags = props.character.tags ?? [];
  return sortRosterTagRows(
    tags.map((t) => ({
      tag: t,
      source: t.used_by_guild_id != null ? 'guild' : 'personal',
    }))
  );
});

const tagsUi = computed(() => sliceRosterTagRowsForDisplay(tagRows.value));
</script>

<template>
  <li
    class="relative w-full max-w-[300px] rounded-xl border bg-card px-3 py-3 transition-colors hover:bg-muted/50 sm:px-4"
    :class="clickable ? 'cursor-pointer' : ''"
    @click="$emit('click')"
  >
    <div class="flex min-w-0 items-center gap-2 pr-11">
      <p
        class="min-w-0 truncate text-base font-semibold leading-tight sm:text-lg"
        :title="character.name"
      >
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

    <div class="flex min-w-0 items-center gap-3 sm:gap-4">
      <Avatar
        :src="character.avatar_url ?? undefined"
        :alt="character.name"
        :fallback="character.name.slice(0, 2).toUpperCase()"
        class="h-24 w-24 shrink-0 sm:h-16 sm:w-16"
      />

      <div class="min-w-0 flex-1">
        <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:gap-6">
          <div class="min-w-0">
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
                class="font-semibold text-primary hover:underline"
                @click.stop
              >
                {{ character.guild.name }}
              </RouterLink>
            </p>

            <div v-if="character.game_classes?.length" class="mt-2 flex flex-wrap items-center gap-1.5">
              <CharacterClassBadge
                v-for="gc in character.game_classes"
                :key="gc.id"
                :game-class="gc"
              />
            </div>

            <div v-if="tagsUi.visible.length" class="mt-2 flex flex-wrap items-center gap-1.5">
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

      <div
        v-if="$slots.actions"
        class="absolute right-2 top-2 flex shrink-0 items-center"
        @click.stop
      >
        <slot name="actions" />
      </div>
    </div>
  </li>
</template>

