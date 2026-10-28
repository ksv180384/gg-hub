import assert from 'node:assert/strict';
import test, { after, before } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';

let server;
let renderCard;

before(async () => {
  server = await createServer({
    configFile: false,
    root: fileURLToPath(new URL('..', import.meta.url)),
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('../src', import.meta.url)),
      },
    },
    server: { middlewareMode: true },
    optimizeDeps: { noDiscovery: true, include: [] },
    cacheDir: 'node_modules/.vite-post-card-tests',
  });
  ({ renderCard } = await server.ssrLoadModule('/tests/helpers/render-post-card.ts'));
});

after(async () => {
  await server?.close();
});

test('image preview has a linked cover, excerpt, and separate post and comments links', async () => {
  const html = await renderCard({
    preview: '<p><img src="/guide.jpg" alt="Босс"></p><h2>Прохождение</h2><p>Первая зона</p>',
    views_count: 89,
    comments_count: 0,
  }, { postTo: '/posts/7', commentsTo: '/posts/7#comments' });

  assert.match(html, /post-preview--with-cover/);
  assert.equal((html.match(/src="\/guide.jpg"/g) ?? []).length, 1);
  assert.match(html, /alt="Босс"/);
  assert.match(html, /post-preview-rich--compact/);
  assert.match(html, /<h2>Прохождение<\/h2>/);
  assert.match(html, /href="\/posts\/7"/);
  assert.match(html, /href="\/posts\/7#comments"/);
  assert.match(html, /Читать пост/);
  assert.match(html, /Комментарии: 0/);
  assert.match(html, /Просмотры: 89/);
});

test('video previews keep their image and playable embed together without clipping', async () => {
  const html = await renderCard({
    preview: '<p><img src="/poster.jpg"></p><div data-video-embed><iframe src="https://www.youtube.com/embed/example?enablejsapi=1"></iframe></div>',
  }, { postTo: '/posts/7' });

  assert.doesNotMatch(html, /post-preview--with-cover|post-preview-rich--compact/);
  assert.match(html, /<iframe src="https:\/\/www.youtube.com\/embed\/example\?enablejsapi=1"/);
  assert.match(html, /data-video-embed/);
  assert.match(html, /src="\/poster.jpg"/);
});

test('cards without a destination keep the preview available and omit read links', async () => {
  const html = await renderCard({
    preview: '<p><img src="/guide.jpg"></p><p>Текст для модерации</p>',
    status_global: 'published',
    status_global_label: 'Опубликован',
    game_name: 'Throne and Liberty',
  }, { showStatus: true, showGame: true });

  assert.doesNotMatch(html, /Читать пост|post-preview-rich--compact|post-preview--with-cover/);
  assert.match(html, /Текст для модерации/);
  assert.match(html, /Опубликован/);
  assert.match(html, /Throne and Liberty/);
  assert.match(html, /Редактировать/);
  assert.match(html, /Опубликовать/);
});

test('plain, missing, and body-only previews render without empty image frames', async () => {
  for (const post of [
    { preview: 'Короткая новость' },
    { preview: null, body: '<p>Текст статьи</p>' },
    { preview: '', body: '' },
  ]) {
    const html = await renderCard(post, { postTo: '/posts/7' });
    assert.doesNotMatch(html, /post-preview--with-cover|<img/);
    assert.match(html, /Читать пост/);
    if (post.preview) {
      assert.match(html, /Короткая новость/);
    }
    if (post.body) {
      assert.match(html, /Текст статьи/);
    }
  }
});

test('author permissions, deleted guild marker, and publication date selection remain intact', async () => {
  const post = {
    published_at_global: '2026-05-12T20:05:00Z',
    published_at_guild: '2026-05-10T20:05:00Z',
    is_global_as_guild: true,
    guild_deleted_at: '2026-05-11T20:05:00Z',
  };
  const publicCard = await renderCard(post, { dateType: 'global' });
  assert.match(publicCard, /datetime="2026-05-12T20:05:00Z"/);
  assert.match(publicCard, / disabled(?:=""| )[^>]*>GGAdmin/);
  assert.match(publicCard, /гильди/i);

  const authorCard = await renderCard(post, { authorUserId: 42 });
  assert.match(authorCard, /datetime="2026-05-10T20:05:00Z"/);
  assert.doesNotMatch(authorCard, / disabled(?:=""| )[^>]*>GGAdmin/);
});
