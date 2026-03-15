import { defineStore } from 'pinia';
import { ref } from 'vue';
import { postsApi } from '@/shared/api/postsApi';

/**
 * Счётчик постов, ожидающих модерации в админ-журнале.
 * Обновляется при загрузке сайдбара и после действий публикации/отклонения/блокировки поста.
 */
export const useAdminJournalStore = defineStore('adminJournal', () => {
  const pendingCount = ref(0);

  async function refreshPendingCount(): Promise<void> {
    try {
      pendingCount.value = await postsApi.getAdminPendingCount();
    } catch {
      pendingCount.value = 0;
    }
  }

  return { pendingCount, refreshPendingCount };
});
