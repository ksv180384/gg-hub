import { onMounted, ref } from 'vue';
import { gamesApi, type GameCatalogItem } from '@/shared/api/gamesApi';

export function useGamesCatalog() {
  const games = ref<GameCatalogItem[]>([]);
  const loading = ref(true);
  const error = ref<string | null>(null);

  async function loadGames() {
    loading.value = true;
    error.value = null;
    try {
      games.value = await gamesApi.getGamesCatalog();
    } catch (e: unknown) {
      const err = e as Error & { message?: string };
      error.value = err.message ?? 'Не удалось загрузить игры';
    } finally {
      loading.value = false;
    }
  }

  onMounted(() => {
    loadGames();
  });

  return {
    games,
    loading,
    error,
    loadGames,
  };
}
