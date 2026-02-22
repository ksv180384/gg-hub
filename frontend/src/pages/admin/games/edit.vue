<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { gamesApi, type Game } from '@/shared/api/gamesApi';
import GameEditTab from './edit/GameEditTab.vue';
import GameClassesTab from './edit/GameClassesTab.vue';
import GameLocalizationsTab from './edit/GameLocalizationsTab.vue';
import GameServersTab from './edit/GameServersTab.vue';

const route = useRoute();
const router = useRouter();
const gameId = computed(() => Number(route.params.id));

const game = ref<Game | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

type TabId = 'game' | 'classes' | 'localizations' | 'servers';
const tabs: { id: TabId; label: string }[] = [
  { id: 'game', label: 'Игра' },
  { id: 'classes', label: 'Классы' },
  { id: 'localizations', label: 'Локализации' },
  { id: 'servers', label: 'Сервера' },
];
const activeTab = ref<TabId>('game');

async function loadGame() {
  const id = gameId.value;
  if (!id || Number.isNaN(id)) {
    router.replace('/admin/games');
    return;
  }
  loading.value = true;
  error.value = null;
  try {
    game.value = await gamesApi.getGame(id);
  } catch (e: unknown) {
    const err = e as Error & { status?: number };
    if (err.status === 404) router.replace('/admin/games');
    else error.value = err.message ?? 'Не удалось загрузить игру';
  } finally {
    loading.value = false;
  }
}

function onGameUpdated(updated: Game) {
  game.value = updated;
}

onMounted(() => loadGame());

watch(gameId, (id) => {
  if (id && !Number.isNaN(id)) loadGame();
});
</script>

<template>
  <div class="container py-8 md:py-12">
    <div class="mx-auto max-w-xl">
      <h1 class="mb-2 text-3xl font-bold tracking-tight">Редактировать игру</h1>
      <p v-if="game" class="mb-6 text-muted-foreground">{{ game.name }}</p>

      <div v-if="loading" class="text-muted-foreground">Загрузка...</div>

      <template v-else-if="game">
        <div v-if="error" class="mb-6 rounded-md bg-destructive/10 p-4 text-sm text-destructive">
          {{ error }}
        </div>

        <nav
          role="tablist"
          class="mb-6 flex gap-1 overflow-x-auto rounded-lg border bg-muted/30 p-1 [-webkit-overflow-scrolling:touch]"
          aria-label="Разделы редактирования"
        >
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :aria-controls="`panel-${tab.id}`"
            :id="`tab-${tab.id}`"
            class="min-h-11 shrink-0 touch-manipulation rounded-md px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :class="
              activeTab === tab.id
                ? 'bg-background text-foreground shadow-sm'
                : 'text-muted-foreground hover:text-foreground'
            "
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </nav>

        <div
          v-show="activeTab === 'game'"
          :id="`panel-game`"
          role="tabpanel"
          :aria-labelledby="`tab-game`"
          class="focus:outline-none"
        >
          <GameEditTab :game="game" @update:game="onGameUpdated" />
        </div>
        <div
          v-show="activeTab === 'classes'"
          :id="`panel-classes`"
          role="tabpanel"
          :aria-labelledby="`tab-classes`"
          class="focus:outline-none"
        >
          <GameClassesTab :game="game" @update:game="onGameUpdated" />
        </div>
        <div
          v-show="activeTab === 'localizations'"
          :id="`panel-localizations`"
          role="tabpanel"
          :aria-labelledby="`tab-localizations`"
          class="focus:outline-none"
        >
          <GameLocalizationsTab :game="game" @update:game="onGameUpdated" />
        </div>
        <div
          v-show="activeTab === 'servers'"
          :id="`panel-servers`"
          role="tabpanel"
          :aria-labelledby="`tab-servers`"
          class="focus:outline-none"
        >
          <GameServersTab :game="game" @update:game="onGameUpdated" />
        </div>
      </template>

      <div v-else class="rounded-lg border border-dashed p-8 text-center text-muted-foreground">
        Игра не найдена.
      </div>
    </div>
  </div>
</template>
