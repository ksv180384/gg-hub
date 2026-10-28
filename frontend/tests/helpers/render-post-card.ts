import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createMemoryHistory, createRouter } from 'vue-router';
import PostCardPreview from '../../src/shared/ui/post/PostCardPreview.vue';
import type { Post } from '../../src/shared/api/postsApi';

export async function renderCard(
  post: Partial<Post>,
  props: Record<string, unknown> = {},
) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
  });
  await router.push('/');
  await router.isReady();

  const app = createSSRApp({
    render: () => h(PostCardPreview, {
      post: {
        id: 7,
        title: 'Гайд по подземелью',
        author_name: 'GGAdmin',
        created_at: '2026-05-12T20:05:00Z',
        ...post,
      } as Post,
      ...props,
    }, {
      headerRight: () => h('button', 'Редактировать'),
      footerRight: () => h('button', 'Опубликовать'),
    }),
  });
  app.use(router);
  return renderToString(app);
}
