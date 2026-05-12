<script setup lang="ts">
import { GamesCatalogPage } from '@/widgets/games-catalog';
import { applyPageSeo, getSiteOrigin } from '@/shared/lib/usePageSeo';

const siteOrigin = getSiteOrigin();

if (typeof window !== 'undefined') {
  applyPageSeo({
    title: 'Игры — gg-hub',
    description: 'Список поддерживаемых игр на gg-hub. Выберите игру — откроется её сайт с каталогом гильдий, журналом и инструментами сообщества.',
    keywords: 'игры, поддерживаемые игры, mmorpg, thr0ne and liberty, aion 2, gg-hub',
    canonicalUrl: `${siteOrigin}/games`,
    ogType: 'website',
  });
}
</script>

<template>
  <GamesCatalogPage />
</template>
