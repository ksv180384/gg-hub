<script setup lang="ts">
import { inject } from 'vue';
import { GamesCatalogPage } from '@/widgets/games-catalog';
import { applyPageSeo } from '@/shared/lib/usePageSeo';
import { getMainSiteOrigin, mainSiteOriginSsrKey } from '@/shared/lib/mainSiteOriginSsr';

const mainSiteOriginFromSsr = inject(mainSiteOriginSsrKey, undefined as string | undefined);
const siteOrigin = getMainSiteOrigin(mainSiteOriginFromSsr);

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
