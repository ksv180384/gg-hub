import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const mainLayoutUrl = new URL('../src/app/layouts/MainLayout.vue', import.meta.url);

test('route loading overlay stays inside main content and does not cover the footer', async () => {
  const source = await readFile(mainLayoutUrl, 'utf8');
  const overlay = source.match(
    /<div\s+v-show="routeLoading\.isLoading"[\s\S]*?>/,
  )?.[0];

  assert.ok(overlay, 'route loading overlay must be present');
  assert.match(source, /<main class="[^"]*\brelative\b[^"]*">/);
  assert.match(overlay, /class="[^"]*\babsolute\b[^"]*\binset-0\b[^"]*"/);
  assert.doesNotMatch(overlay, /\bfixed\b|\bbottom-0\b|md:top-14|md:left-56/);
});

test('landing footer background is limited to the main-domain home page', async () => {
  const source = await readFile(mainLayoutUrl, 'utf8');

  assert.match(
    source,
    /const useLandingFooter = computed\(\s*\(\) => route\.name === 'home' && siteContext\.mode === 'main',\s*\);/,
  );
  assert.match(source, /useLandingFooter && 'landing-page'/);
  assert.match(source, /\.landing-page \.landing-home-footer/);
  assert.doesNotMatch(source, /^\.landing-home-footer/m);
});
