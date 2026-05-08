<script setup lang="ts">
import { Card, CardContent, Badge, BackIconButton } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { applyPageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';

type TabId = 'about' | 'charter';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const siteOrigin = getSiteOrigin();
let cleanupSeo: (() => void) | null = null;

const activeTab = ref<TabId>('about');

const visibleTabs: { id: TabId; label: string }[] = [
  { id: 'about', label: 'О гильдии' },
  { id: 'charter', label: 'Устав' },
];

function setTab(id: TabId) {
  activeTab.value = id;
  const { tab: _removed, ...rest } = route.query;
  router.replace({
    name: 'guild-info',
    params: { id: String(guildId.value) },
    ...(Object.keys(rest).length > 0 ? { query: rest } : {}),
  });
}

watch(
  () => String(route.query.tab ?? ''),
  (tab) => {
    if (tab === 'roster') {
      const { tab: _t, ...rest } = route.query;
      router.replace({
        name: 'guild-info',
        params: { id: String(guildId.value) },
        ...(Object.keys(rest).length > 0 ? { query: rest } : {}),
      });
      activeTab.value = 'about';
      return;
    }
    if (tab === 'charter') {
      activeTab.value = 'charter';
    }
  },
  { immediate: true },
);

async function loadGuild() {
  if (!guildId.value) return;
  loading.value = true;
  error.value = null;
  try {
    guild.value = await guildsApi.getGuild(guildId.value);
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

function stripHtmlToText(html: string): string {
  if (!html) return '';
  // В about_text может быть HTML; делаем безопасный plain-text для description.
  const div = document.createElement('div');
  div.innerHTML = html;
  return (div.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function truncate(text: string, maxLen: number): string {
  const s = (text ?? '').trim();
  if (s.length <= maxLen) return s;
  return `${s.slice(0, Math.max(0, maxLen - 1)).trimEnd()}…`;
}

watch(
  () => guild.value,
  (g) => {
    if (typeof window === 'undefined') return;

    cleanupSeo?.();
    cleanupSeo = null;

    if (!g) return;

    const gameName = (g.game?.name ?? '').trim();
    const serverName = (g.server?.name ?? '').trim();
    const titleBase = gameName ? `${g.name} — гильдия ${gameName}` : `${g.name} — гильдия`;
    const title = `${titleBase} — gg-hub`;

    const aboutText = g.about_text ? stripHtmlToText(g.about_text) : '';
    const tagText = (g.tags ?? []).map((t) => t.name).filter(Boolean).slice(0, 8).join(', ');
    const parts = [
      aboutText,
      gameName ? `Игра: ${gameName}.` : '',
      serverName ? `Сервер: ${serverName}.` : '',
      tagText ? `Теги: ${tagText}.` : '',
      'Профиль гильдии на gg-hub.',
    ].filter(Boolean);
    const description = truncate(parts.join(' '), 160);

    const keywordsParts = [
      g.name,
      gameName ? `гильдия ${gameName}` : 'гильдия',
      serverName ? `сервер ${serverName}` : '',
      ...(g.tags ?? []).map((t) => t.name),
      'каталог гильдий',
      'gg-hub',
    ]
      .map((s) => s?.trim())
      .filter((s): s is string => !!s);
    const keywords = [...new Set(keywordsParts)].slice(0, 18).join(', ');

    const canonicalUrl = `${siteOrigin}/guilds/${g.id}/info`;
    const ogImageUrl = g.logo_url ? storageImageUrl(g.logo_url) : undefined;

    cleanupSeo = applyPageSeo({
      title,
      description,
      keywords,
      canonicalUrl,
      ogType: 'website',
      ogImageUrl,
    });
  },
  { immediate: true },
);

const logoDisplayUrl = computed(() => {
  return guild.value?.logo_url ? storageImageUrl(guild.value.logo_url) : null;
});

const logoErrored = ref(false);

watch(
  () => guild.value?.logo_url ?? null,
  () => {
    logoErrored.value = false;
  },
);

const showLogoImage = computed(() => !!logoDisplayUrl.value && !logoErrored.value);

const guildInitials = computed(() => {
  const name = (guild.value?.name ?? '').trim();
  if (!name) return 'G';
  const parts = name.split(/\s+/).filter(Boolean);
  const letters = parts.slice(0, 2).map((p) => (p[0] ?? '').toUpperCase()).join('');
  return letters || name[0]!.toUpperCase();
});

function goToApplication() {
  router.push({ name: 'guild-application-form', params: { id: String(guild.value!.id) } });
}
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-4xl">
      <!-- Mobile: одна плавающая кнопка справа -->
      <div class="fixed top-[100px] right-8 z-30 md:hidden">
        <BackIconButton
          aria-label="К списку гильдий"
          title="К списку гильдий"
          @click="router.push({ name: 'guilds' })"
        >
        </BackIconButton>
      </div>

      <div class="relative flex flex-col md:flex-row md:items-start md:gap-3">
        <!-- Desktop: стрелка слева от контента -->
        <div class="sticky top-[100px] z-30 hidden shrink-0 self-start md:block">
          <BackIconButton
            aria-label="К списку гильдий"
            title="К списку гильдий"
            @click="router.push({ name: 'guilds' })"
          >
          </BackIconButton>
        </div>

        <div class="min-w-0 w-full flex-1">
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
                  class="relative flex h-[290px] w-full max-w-[290px] shrink-0 flex-col items-center justify-center overflow-hidden rounded-lg"
                  :class="showLogoImage ? 'bg-muted/20' : 'bg-gradient-to-br from-primary/15 via-muted/20 to-muted/40'"
                >
                  <img
                    v-if="showLogoImage"
                    :src="logoDisplayUrl"
                    alt="Логотип гильдии"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @error="logoErrored = true"
                  />
                  <div v-else class="flex h-full w-full flex-col items-center justify-center gap-3 text-muted-foreground">
                    <div
                      class="flex h-20 w-20 items-center justify-center rounded-full bg-muted/60 text-2xl font-semibold text-foreground/80 ring-1 ring-border"
                      :aria-label="`Логотип гильдии отсутствует. Инициалы: ${guildInitials}`"
                    >
                      {{ guildInitials }}
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        class="opacity-70"
                      >
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                      </svg>
                      <span>Логотип не добавлен</span>
                    </div>
                  </div>
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
                    </span>
                    <span class="hidden md:inline">{{ t.label }}</span>
                  </button>
                </div>

                <!-- Вкладка: О гильдии -->
                <Card v-show="activeTab === 'about'" class="mb-6 border-0 shadow-none">
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
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
