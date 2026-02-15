import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { contextApi, type SiteContextData } from '@/shared/api/contextApi';

export const useSiteContextStore = defineStore('siteContext', () => {
  const data = ref<SiteContextData | null>(null);
  const loading = ref(false);

  const mode = computed(() => data.value?.mode ?? 'main');
  const subdomain = computed(() => data.value?.subdomain ?? null);
  const game = computed(() => data.value?.game ?? null);

  const isAdmin = computed(() => mode.value === 'admin');
  const isGameSubdomain = computed(() => mode.value === 'game');

  async function fetchContext(): Promise<SiteContextData | null> {
    loading.value = true;
    try {
      data.value = await contextApi.getContext();
      return data.value;
    } catch {
      data.value = { mode: 'main', subdomain: null, game: null };
      return data.value;
    } finally {
      loading.value = false;
    }
  }

  return {
    data,
    loading,
    mode,
    subdomain,
    game,
    isAdmin,
    isGameSubdomain,
    fetchContext,
  };
});
