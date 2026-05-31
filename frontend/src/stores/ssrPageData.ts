import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Post } from '@/shared/api/postsApi';
import type { Guild, GuildApplicationFormData } from '@/shared/api/guildsApi';

export const useSsrPageDataStore = defineStore('ssrPageData', () => {
  const globalPost = ref<Post | null>(null);
  const journalPosts = ref<Post[] | null>(null);
  const guildInfo = ref<Guild | null>(null);
  const guildApplicationForm = ref<GuildApplicationFormData | null>(null);

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

  return {
    globalPost,
    journalPosts,
    guildInfo,
    guildApplicationForm,
    setGlobalPost,
    setJournalPosts,
    setGuildInfo,
    setGuildApplicationForm,
  };
});
