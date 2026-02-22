<script setup lang="ts">
import { Button, Badge } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import type { Guild } from '@/shared/api/guildsApi';
import { computed } from 'vue';
import { useRouter } from 'vue-router';

const props = withDefaults(
  defineProps<{
    guild: Guild;
    /** Режим списка: кнопки «Подробнее» и «Настройки» вместо «Вступить»/«Набор закрыт». */
    listMode?: boolean;
    /** В режиме списка — показывать кнопку «Настройки». */
    canAccessSettings?: boolean;
  }>(),
  { listMode: false, canAccessSettings: false }
);

const router = useRouter();

const logoUrl = computed(() => {
  const url = props.guild.logo_card_url ?? props.guild.logo_url;
  return url ? storageImageUrl(url) : null;
});

const leaderName = computed(() => props.guild.leader?.name ?? '—');
const serverName = computed(() => props.guild.server?.name ?? '—');
const localizationName = computed(() => props.guild.localization?.name ?? '—');

function goToApplication() {
  router.push({ name: 'guild-applications', params: { id: String(props.guild.id) } });
}

function goToDetails() {
  router.push({ name: 'guild-show', params: { id: String(props.guild.id) } });
}

function goToSettings() {
  router.push({ name: 'guild-settings', params: { id: String(props.guild.id) } });
}
</script>

<template>
  <article
    class="guild-card flex w-80 flex-shrink-0 flex-col overflow-hidden rounded-[2rem] bg-background p-2 font-sans shadow-[0_0_0_4px_rgba(0,0,0,0.08)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 dark:shadow-[0_0_0_4px_rgba(255,255,255,0.12)]"
    tabindex="0"
  >
    <!-- Изображение гильдии -->
    <div class="guild-card__img-wrap relative aspect-square w-full overflow-hidden rounded-[1.5rem]">
      <Badge
        v-if="listMode && guild.is_recruiting"
        variant="outline"
        class="absolute right-2 top-2 z-10 border-white/50 bg-black/40 text-white backdrop-blur-sm"
      >
        Набор
      </Badge>
      <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="guild.name"
        class="guild-card__img block h-full w-full object-cover object-[50%_10%]"
      />
      <div
        v-else
        class="guild-card__img guild-card__img--placeholder flex h-full w-full items-center justify-center bg-muted text-muted-foreground"
      >
        <span class="text-4xl font-semibold">{{ guild.name.charAt(0) }}</span>
      </div>
    </div>

    <!-- Секция с информацией -->
    <section class="guild-card__section flex flex-1 flex-col rounded-b-[1.5rem] px-3 pb-3 pt-4">
      <h2 class="guild-card__section-title mb-1.5 mt-0 text-lg font-semibold text-foreground">
        {{ guild.name }}
      </h2>
      <div class="guild-card__info space-y-0.5 text-sm text-muted-foreground">
        <p class="m-0 leading-tight">
          <span class="font-medium text-foreground/90">Лидер гильдии:</span>
          {{ leaderName }}
        </p>
        <p class="m-0 leading-tight">
          <span class="font-medium text-foreground/90">Сервер:</span>
          {{ serverName }}
        </p>
        <p class="m-0 leading-tight">
          <span class="font-medium text-foreground/90">Локализатор:</span>
          {{ localizationName }}
        </p>
      </div>
      <div class="mt-2 flex flex-1 flex-wrap items-end justify-between gap-2">
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
              v-if="canAccessSettings"
              size="sm"
              variant="outline"
              class="shrink-0 rounded-xl px-4"
              @click="goToSettings"
            >
              Настройки
            </Button>
            <Button
              size="sm"
              variant="outline"
              class="shrink-0 rounded-xl px-4"
              @click="goToDetails"
            >
              Подробнее
            </Button>
          </template>
          <template v-else>
            <Button
              v-if="guild.is_recruiting"
              size="sm"
              class="shrink-0 rounded-xl rounded-br-2xl rounded-tr-2xl px-4"
              @click="goToApplication"
            >
              Вступить
            </Button>
            <Button
              v-else
              size="sm"
              variant="secondary"
              disabled
              class="shrink-0 rounded-xl rounded-br-2xl rounded-tr-2xl px-4"
            >
              Набор закрыт
            </Button>
          </template>
        </div>
      </div>
    </section>
  </article>
</template>

<style scoped>
.guild-card {
  min-height: 28rem;
}
</style>
