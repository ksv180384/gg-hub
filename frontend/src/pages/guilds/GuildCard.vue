<script setup lang="ts">
import { Button, Badge, Tooltip } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { Guild } from '@/shared/api/guildsApi';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = withDefaults(
  defineProps<{
    guild: Guild;
    /** Режим списка: иконка «Подробнее» и при наборе — «Подать заявку». */
    listMode?: boolean;
    /** Показывать название игры на карточке (например, когда список гильдий без субдомена). */
    showGameName?: boolean;
  }>(),
  { listMode: false, showGameName: false }
);

const router = useRouter();

const logoUrl = computed(() => {
  const url = props.guild.logo_card_url ?? props.guild.logo_url;
  return url ? storageImageUrl(url) : null;
});

const leaderName = computed(() => props.guild.leader?.name ?? '—');
const serverName = computed(() => props.guild.server?.name ?? '—');
const localizationName = computed(() => props.guild.localization?.name ?? '—');
const gameName = computed(() => props.guild.game?.name ?? '—');
const guildInitial = computed(() => props.guild.name.trim().charAt(0).toUpperCase() || 'G');
const visibleTags = computed(() => (props.guild.tags ?? []).slice(0, 3));

function goToApplication() {
  router.push({ name: 'guild-application-form', params: { id: String(props.guild.id) } });
}

function goToDetails() {
  router.push({ name: 'guild-info', params: { id: String(props.guild.id) } });
}

</script>

<template>
  <article
    class="guild-card flex min-h-[12.5rem] w-full flex-col rounded-lg border border-border bg-background p-4 font-sans shadow-sm transition-colors hover:border-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    tabindex="0"
  >
    <div class="flex items-start gap-4">
      <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted/40">
        <img
          v-if="logoUrl"
          :src="logoUrl"
          :alt="guild.name"
          class="block h-full w-full object-cover"
        />
        <div
          v-else
          class="flex h-full w-full items-center justify-center bg-primary/10 text-primary"
        >
          <span class="text-2xl font-semibold">{{ guildInitial }}</span>
        </div>
      </div>
      <div
        class="min-w-0 flex-1 pt-0.5"
      >
        <div class="flex min-w-0 flex-wrap items-center gap-2">
          <h2 class="min-w-0 truncate text-base font-semibold leading-6 text-foreground">
            {{ guild.name }}
          </h2>
          <Badge
            v-if="listMode && guild.is_recruiting"
            variant="outline"
            class="h-6 border-primary/25 bg-primary/10 px-2 text-xs font-medium text-primary"
          >
            Набор
          </Badge>
        </div>
        <p
          v-if="showGameName && guild.game"
          class="mt-0.5 truncate text-xs font-medium text-muted-foreground"
        >
          {{ gameName }}
        </p>
        <div class="mt-3 space-y-1 text-sm text-muted-foreground">
          <p class="m-0 leading-5">
            <span>Лидер гильдии: </span>
            <span class="font-medium text-foreground">{{ leaderName }}</span>
          </p>
          <p class="m-0 leading-5">
            <span>Сервер: </span>
            <span class="font-medium text-foreground">{{ serverName }}</span>
          </p>
          <p class="m-0 leading-5">
            <span>Локализация: </span>
            <span class="font-medium text-foreground">{{ localizationName }}</span>
          </p>
        </div>
      </div>
    </div>

    <section class="mt-3 flex flex-1 flex-col">
      <div v-if="visibleTags.length" class="flex flex-wrap items-center gap-1">
        <Badge
          v-for="tag in visibleTags"
          :key="tag.id"
          variant="outline"
          class="h-6 px-2 text-xs font-normal"
        >
          {{ tag.name }}
        </Badge>
      </div>
      <div class="mt-4 flex flex-1 flex-wrap items-end justify-between gap-2">
        <span
          v-if="guild.members_count != null"
          class="flex items-center gap-1.5 text-xs text-muted-foreground"
          aria-label="Участников"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          {{ guild.members_count }}
        </span>
        <div class="flex gap-2">
          <template v-if="listMode">
            <Button
              v-if="guild.is_recruiting"
              size="sm"
              class="h-8 shrink-0 px-4"
              @click="goToApplication"
            >
              Подать заявку
            </Button>
            <Tooltip content="Подробнее" side="top">
              <Button
                size="icon"
                variant="outline"
                class="h-8 w-8 shrink-0"
                aria-label="Подробнее"
                @click="goToDetails"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M5 12h14" />
                  <path d="m12 5 7 7-7 7" />
                </svg>
              </Button>
            </Tooltip>
          </template>
          <template v-else>
            <Button
              v-if="guild.is_recruiting"
              size="sm"
              class="h-8 shrink-0 px-4"
              @click="goToApplication"
            >
              Вступить
            </Button>
            <Button
              v-else
              size="sm"
              variant="secondary"
              disabled
              class="h-8 shrink-0 px-4"
            >
              Набор закрыт
            </Button>
          </template>
        </div>
      </div>
    </section>
  </article>
</template>
