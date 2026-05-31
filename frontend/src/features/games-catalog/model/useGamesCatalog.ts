import { onMounted, onServerPrefetch, ref } from 'vue';
import { gamesApi, type GameCatalogItem } from '@/shared/api/gamesApi';
import { useSsrPageDataStore } from '@/stores/ssrPageData';

export function useGamesCatalog() {
  const ssrPageData = useSsrPageDataStore();
  const initialGames = ssrPageData.gamesCatalog;
  const games = ref<GameCatalogItem[]>(initialGames ?? []);
  const loading = ref(initialGames == null);
  const error = ref<string | null>(null);

  async function loadGames() {
    loading.value = true;
    error.value = null;
    try {
      games.value = await gamesApi.getGamesCatalog();
      ssrPageData.setGamesCatalog(games.value);
    } catch (e: unknown) {
      const err = e as Error & { message?: string };
      error.value = err.message ?? 'Не удалось загрузить игры';
    } finally {
      loading.value = false;
    }
  }

  onServerPrefetch(async () => {
    if (ssrPageData.gamesCatalog == null) {
      await loadGames();
    }
  });

  onMounted(() => {
    if (ssrPageData.gamesCatalog == null) {
      loadGames();
    }
  });

  return {
    games,
    loading,
    error,
    loadGames,
  };
}
