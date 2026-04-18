<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button, Badge } from '@/shared/ui';
import Avatar from '@/shared/ui/avatar/Avatar.vue';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { guildsApi, type Guild, type GuildRosterMember } from '@/shared/api/guildsApi';
import { rosterTagBadgeClass, rosterTagDisplayRows } from '@/shared/lib/rosterTagDisplay';
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';

type TabId = 'about' | 'charter' | 'roster';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const roster = ref<GuildRosterMember[]>([]);
const rosterLoading = ref(false);
const rosterFetched = ref(false);
const rosterErrorStatus = ref<number | null>(null);

const activeTab = ref<TabId>('about');

function avatarFallback(name: string): string {
  if (!name?.trim()) return '?';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

/** Вкладка состава — только при настройке «Показывать состав гильдии всем пользователям». */
const showRosterSection = computed(() => guild.value?.show_roster_to_all ?? false);

const rosterNeedsLogin = computed(
  () =>
    !!guild.value?.show_roster_to_all &&
    rosterFetched.value &&
    !rosterLoading.value &&
    rosterErrorStatus.value === 401
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

const visibleTabs = computed(() => {
  const all: { id: TabId; label: string }[] = [
    { id: 'about', label: 'О гильдии' },
    { id: 'charter', label: 'Устав' },
    { id: 'roster', label: 'Состав гильдии' },
  ];
  if (!showRosterSection.value) {
    return all.filter((t) => t.id !== 'roster');
  }
  return all;
});

function setTab(id: TabId) {
  if (id === 'roster' && !showRosterSection.value) return;
  activeTab.value = id;
  if (id === 'roster') {
    router.replace({
      name: 'guild-info',
      params: { id: String(guildId.value) },
      query: { ...route.query, tab: 'roster' },
    });
  } else {
    const { tab: _removed, ...rest } = route.query;
    router.replace({
      name: 'guild-info',
      params: { id: String(guildId.value) },
      ...(Object.keys(rest).length > 0 ? { query: rest } : {}),
    });
  }
}

watch(
  () => [String(route.query.tab ?? ''), showRosterSection.value] as const,
  ([tab, showRoster]) => {
    if (tab === 'roster' && showRoster) {
      activeTab.value = 'roster';
    }
  },
  { immediate: true }
);

watch(visibleTabs, (tabs) => {
  if (!tabs.some((t) => t.id === activeTab.value)) {
    activeTab.value = 'about';
  }
});

watch(showRosterSection, (show) => {
  if (!show && activeTab.value === 'roster') {
    activeTab.value = 'about';
    if (route.query.tab === 'roster') {
      const { tab: _t, ...rest } = route.query;
      router.replace({
        name: 'guild-info',
        params: { id: String(guildId.value) },
        ...(Object.keys(rest).length > 0 ? { query: rest } : {}),
      });
    }
  }
});

async function loadGuild() {
  if (!guildId.value) return;
  loading.value = true;
  error.value = null;
  rosterFetched.value = false;
  roster.value = [];
  rosterErrorStatus.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
    if (guild.value.show_roster_to_all) {
      void loadRoster();
    } else {
      rosterFetched.value = true;
      rosterLoading.value = false;
      roster.value = [];
      rosterErrorStatus.value = null;
    }
  } catch (e: unknown) {
    const err = e as { status?: number };
    if (err.status === 404) {
      router.replace('/guilds');
      return;
    }
    error.value = 'Не удалось загрузить гильдию';
  } finally {
    loading.value = false;
  }
}

watch(guildId, () => {
  loadGuild();
}, { immediate: true });

const logoDisplayUrl = computed(() => {
  return guild.value?.logo_url ? storageImageUrl(guild.value.logo_url) : null;
});

function goToApplication() {
  router.push({ name: 'guild-application-form', params: { id: String(guild.value!.id) } });
}
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="sm" @click="router.push({ name: 'guilds' })">
          ← К списку гильдий
        </Button>
      </div>

      <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-destructive">
        {{ error }}
      </div>

      <div v-if="loading" class="text-muted-foreground">Загрузка…</div>

      <template v-else-if="guild">
        <div class="flex flex-col gap-6 md:flex-row md:items-start">
          <!-- Левая колонка: название, логотип, лидер, участники -->
          <div class="flex w-full shrink-0 flex-col items-center order-1 md:order-1 md:w-[290px]">
            <h1 class="mb-3 w-full text-center text-xl font-bold md:text-2xl">{{ guild.name }}</h1>
            <div
              class="relative flex h-[290px] w-full max-w-[290px] shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg bg-muted/20"
            >
              <img
                v-if="logoDisplayUrl"
                :src="logoDisplayUrl"
                alt="Логотип гильдии"
                class="h-full w-full object-cover"
              />
              <span v-else class="text-sm text-muted-foreground">Нет логотипа</span>
            </div>
            <div
              v-if="guild.tags?.length"
              class="mt-3 flex w-full max-w-[290px] flex-wrap justify-center gap-1.5 px-1"
            >
              <Badge
                v-for="tag in guild.tags"
                :key="tag.id"
                variant="outline"
                class="text-xs font-normal"
              >
                {{ tag.name }}
              </Badge>
            </div>
            <div class="mt-3 flex w-full max-w-[290px] flex-col items-center gap-1 text-center text-sm">
              <div class="font-medium text-foreground">
                Лидер: {{ guild.leader?.name ?? '—' }}
              </div>
              <div class="text-muted-foreground">
                Участников: {{ guild.members_count ?? 0 }}
              </div>
              <Button
                v-if="guild.is_recruiting"
                size="sm"
                class="mt-3 shrink-0"
                @click="goToApplication"
              >
                Подать заявку
              </Button>
            </div>
          </div>

          <!-- Правая колонка: табы и контент -->
          <div class="min-w-0 flex-1 order-2 md:order-2">
            <div class="mb-4 flex flex-wrap gap-1 border-b">
              <button
                v-for="t in visibleTabs"
                :key="t.id"
                type="button"
                :aria-label="t.label"
                class="flex items-center justify-center gap-2 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium transition-colors md:justify-start md:px-4"
                :class="
                  activeTab === t.id
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="setTab(t.id)"
              >
                <span class="flex shrink-0 md:hidden" aria-hidden="true">
                  <svg v-if="t.id === 'about'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 16v-4" />
                    <path d="M12 8h.01" />
                  </svg>
                  <svg v-else-if="t.id === 'charter'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <path d="M14 2v6h6" />
                    <path d="M16 13H8" />
                    <path d="M16 17H8" />
                    <path d="M10 9H8" />
                  </svg>
                  <svg v-else-if="t.id === 'roster'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                  </svg>
                </span>
                <span class="hidden md:inline">{{ t.label }}</span>
              </button>
            </div>

            <!-- Вкладка: О гильдии -->
            <Card v-show="activeTab === 'about'" class="mb-6 border-0 shadow-none">
              <CardHeader>
                <CardTitle>О гильдии</CardTitle>
              </CardHeader>
              <CardContent>
                <div
                  v-if="guild.about_text"
                  class="prose prose-sm max-w-none text-muted-foreground dark:prose-invert [&_p]:my-2 [&_a]:text-blue-600 [&_a]:underline [&_a]:decoration-blue-600/40 [&_a]:underline-offset-2 hover:[&_a]:text-blue-700 dark:[&_a]:text-blue-400 dark:hover:[&_a]:text-blue-300 dark:[&_a]:decoration-blue-400/50 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-foreground [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:my-2 [&_ul]:space-y-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:my-2 [&_ol]:space-y-1 [&_li]:my-0.5"
                  v-html="guild.about_text"
                />
                <p v-else class="text-sm text-muted-foreground">—</p>
              </CardContent>
            </Card>

            <!-- Вкладка: Устав -->
            <Card v-show="activeTab === 'charter'" class="mb-6 border-0 shadow-none">
              <CardHeader>
                <CardTitle>Устав</CardTitle>
              </CardHeader>
              <CardContent>
                <p
                  v-if="guild.charter_text"
                  class="whitespace-pre-wrap text-sm text-muted-foreground"
                >
                  {{ guild.charter_text }}
                </p>
                <p v-else class="text-sm text-muted-foreground">—</p>
              </CardContent>
            </Card>

            <!-- Вкладка: Состав гильдии (публичный просмотр при show_roster_to_all) -->
            <Card
              v-show="activeTab === 'roster' && showRosterSection"
              class="mb-6 border-0 shadow-none"
              aria-labelledby="guild-info-roster-heading"
            >
              <CardHeader>
                <CardTitle id="guild-info-roster-heading">Состав гильдии</CardTitle>
              </CardHeader>
              <CardContent>
                <p v-if="guild?.show_roster_to_all && rosterLoading" class="text-sm text-muted-foreground">
                  Загрузка состава…
                </p>
                <p v-else-if="rosterNeedsLogin" class="text-sm text-muted-foreground">
                  Состав открыт для просмотра всем пользователям сайта.
                  <RouterLink
                    :to="{ name: 'login', query: { redirect: route.fullPath } }"
                    class="font-medium text-primary underline-offset-4 hover:underline"
                  >
                    Войдите в аккаунт
                  </RouterLink>
                  , чтобы увидеть список участников.
                </p>
                <p
                  v-else-if="rosterFetched && rosterErrorStatus != null && rosterErrorStatus !== 401 && rosterErrorStatus !== 403"
                  class="text-sm text-destructive"
                >
                  Не удалось загрузить состав. Попробуйте обновить страницу.
                </p>
                <template v-else-if="!rosterNeedsLogin && rosterFetched && !rosterLoading && rosterErrorStatus === null">
                  <p v-if="roster.length === 0" class="text-sm text-muted-foreground">
                    В гильдии пока никого нет.
                  </p>
                  <div
                    v-else
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                  >
                    <RouterLink
                      v-for="member in roster"
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
                          <div class="flex flex-wrap gap-1">
                            <Badge
                              v-for="row in rosterTagDisplayRows(member)"
                              :key="row.source + '-' + row.tag.id"
                              variant="outline"
                              :class="[rosterTagBadgeClass(row.source, row.tag), 'text-xs']"
                            >
                              {{ row.tag.name }}
                            </Badge>
                          </div>
                        </CardContent>
                      </Card>
                    </RouterLink>
                  </div>
                </template>
              </CardContent>
            </Card>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
