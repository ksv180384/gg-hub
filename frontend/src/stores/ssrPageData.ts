import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Post } from '@/shared/api/postsApi';
import type { Guild, GuildApplicationFormData } from '@/shared/api/guildsApi';
import type { GameCatalogItem } from '@/shared/api/gamesApi';

export const useSsrPageDataStore = defineStore('ssrPageData', () => {
  const globalPost = ref<Post | null>(null);
  const journalPosts = ref<Post[] | null>(null);
  const guildInfo = ref<Guild | null>(null);
  const guildApplicationForm = ref<GuildApplicationFormData | null>(null);
  const gamesCatalog = ref<GameCatalogItem[] | null>(null);

  function setGlobalPost(post: Post | null): void {
    globalPost.value = post;
  }

  function setJournalPosts(posts: Post[] | null): void {
    journalPosts.value = posts;
  }

  function setGuildInfo(guild: Guild | null): void {
    guildInfo.value = guild;
  }

  function setGuildApplicationForm(formData: GuildApplicationFormData | null): void {
    guildApplicationForm.value = formData;
  }

  function setGamesCatalog(games: GameCatalogItem[] | null): void {
    gamesCatalog.value = games;
  }

  return {
    globalPost,
    journalPosts,
    guildInfo,
    guildApplicationForm,
    gamesCatalog,
    setGlobalPost,
    setJournalPosts,
    setGuildInfo,
    setGuildApplicationForm,
    setGamesCatalog,
  };
});
