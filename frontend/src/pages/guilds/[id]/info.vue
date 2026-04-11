<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/shared/ui';
import { storageImageUrl } from '@/shared/lib/storageImageUrl';
import { guildsApi, type Guild } from '@/shared/api/guildsApi';
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

type TabId = 'about' | 'charter';

const route = useRoute();
const router = useRouter();

const guildId = computed(() => Number(route.params.id));
const guild = ref<Guild | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const tabs: { id: TabId; label: string }[] = [
  { id: 'about', label: 'О гильдии' },
  { id: 'charter', label: 'Устав' },
];

const activeTab = ref<TabId>('about');

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
                v-for="t in tabs"
                :key="t.id"
                type="button"
                :aria-label="t.label"
                class="flex items-center justify-center gap-2 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium transition-colors md:justify-start md:px-4"
                :class="
                  activeTab === t.id
                    ? 'border-primary text-primary'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = t.id"
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
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
