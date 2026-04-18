<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { Card, CardContent, Badge } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { guildsApi, type Guild, type GuildRosterMember } from '@/shared/api/guildsApi';
import {
  rosterTagBadgeClass,
  rosterTagDisplayRows,
  sliceRosterTagRowsForDisplay,
} from '@/shared/lib/rosterTagDisplay';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));

const guild = ref<Guild | null>(null);
const guildLoading = ref(true);
const guildError = ref<string | null>(null);

const roster = ref<GuildRosterMember[]>([]);
const rosterLoading = ref(false);
const rosterFetched = ref(false);
const rosterErrorStatus = ref<number | null>(null);

const rosterDisplayItems = computed(() =>
  roster.value.map((member) => ({
    member,
    tagsUi: sliceRosterTagRowsForDisplay(rosterTagDisplayRows(member)),
  }))
);

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

const rosterNeedsLogin = computed(
  () =>
    rosterFetched.value && !rosterLoading.value && rosterErrorStatus.value === 401
);

const accessDenied = computed(
  () =>
    rosterFetched.value && !rosterLoading.value && rosterErrorStatus.value === 403
);

async function loadRoster() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  rosterLoading.value = true;
  rosterErrorStatus.value = null;
  roster.value = [];
  try {
    roster.value = await guildsApi.getGuildRoster(guildId.value);
  } catch (e: unknown) {
    const err = e as { status?: number };
    rosterErrorStatus.value = err.status ?? -1;
  } finally {
    rosterLoading.value = false;
    rosterFetched.value = true;
  }
}

async function loadGuild() {
  if (!guildId.value || Number.isNaN(guildId.value)) return;
  guildLoading.value = true;
  guildError.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
  } catch (e: unknown) {
    const err = e as { status?: number };
    if (err.status === 404) {
      router.replace('/guilds');
      return;
    }
    guildError.value = 'Не удалось загрузить гильдию';
  } finally {
    guildLoading.value = false;
  }
}

watch(guildId, async () => {
  rosterFetched.value = false;
  roster.value = [];
  rosterErrorStatus.value = null;
  await loadGuild();
  if (guild.value) {
    void loadRoster();
  }
}, { immediate: true });
</script>

<template>
  <div class="container py-4 md:py-8">
    <div class="mx-auto max-w-4xl">
      <div v-if="guildError" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ guildError }}
      </div>

      <div v-if="guildLoading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="guild">
        <h1 class="mb-6 text-2xl font-bold md:text-3xl">
          Состав: {{ guild.name }}
        </h1>

        <p v-if="rosterLoading" class="text-sm text-muted-foreground">
          Загрузка состава…
        </p>
        <p v-else-if="rosterNeedsLogin" class="text-sm text-muted-foreground">
          <RouterLink
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="font-medium text-primary underline-offset-4 hover:underline"
          >
            Войдите в аккаунт
          </RouterLink>
          , чтобы увидеть список участников.
        </p>
        <p v-else-if="accessDenied" class="text-sm text-muted-foreground">
          Состав гильдии доступен только её участникам или при открытом показе состава в настройках гильдии.
        </p>
        <p
          v-else-if="rosterFetched && rosterErrorStatus != null && rosterErrorStatus !== 401 && rosterErrorStatus !== 403"
          class="text-sm text-destructive"
        >
          Не удалось загрузить состав. Попробуйте обновить страницу.
        </p>
        <template v-else-if="!rosterNeedsLogin && !accessDenied && rosterFetched && !rosterLoading && rosterErrorStatus === null">
          <p v-if="roster.length === 0" class="text-sm text-muted-foreground">
            В гильдии пока никого нет.
          </p>
          <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
          >
            <RouterLink
              v-for="{ member, tagsUi } in rosterDisplayItems"
              :key="member.character_id"
              :to="{ name: 'guild-roster-member', params: { id: String(guildId), characterId: String(member.character_id) } }"
              class="block transition-opacity hover:opacity-90 focus-visible:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <Card class="h-full overflow-hidden">
                <CardContent class="flex flex-col items-start gap-3 p-4">
                  <div class="flex w-full items-center gap-3">
                    <Avatar
                      :src="member.avatar_url ?? undefined"
                      :alt="member.name"
                      :fallback="avatarFallback(member.name)"
                      class="h-12 w-12 shrink-0 md:h-14 md:w-14"
                    />
                    <div class="min-w-0 flex-1">
                      <p class="truncate font-medium">{{ member.name }}</p>
                      <Badge
                        v-if="member.guild_role"
                        variant="secondary"
                        class="mt-1 text-xs"
                      >
                        {{ member.guild_role.name }}
                      </Badge>
                    </div>
                  </div>
                  <div v-if="member.game_classes.length > 0" class="flex flex-wrap gap-1">
                    <Badge
                      v-for="gc in member.game_classes"
                      :key="gc.id"
                      variant="outline"
                      class="text-xs"
                    >
                      {{ gc.name_ru ?? gc.name }}
                    </Badge>
                  </div>
                  <div class="flex flex-wrap items-center gap-1">
                    <Badge
                      v-for="row in tagsUi.visible"
                      :key="row.source + '-' + row.tag.id"
                      variant="outline"
                      :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs']"
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
                </CardContent>
              </Card>
            </RouterLink>
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
