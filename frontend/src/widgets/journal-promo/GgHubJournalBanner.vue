<script setup lang="ts">
import { computed, inject } from 'vue';
import { getMainSiteOrigin, mainSiteOriginSsrKey } from '@/shared/lib/mainSiteOriginSsr';

defineProps<{
  /** mobile: под лентой; desktop: правая колонка со sticky */
  variant: 'mobile' | 'desktop';
}>();

const mainSiteOriginFromSsr = inject(mainSiteOriginSsrKey, undefined as string | undefined);
const mainSiteHref = computed(() => `${getMainSiteOrigin(mainSiteOriginFromSsr)}/`);

const imgClass =
  'mx-auto w-full max-w-[min(100%,20rem)] rounded-lg border border-border/60 shadow-md';
</script>

<template>
  <aside
    v-if="variant === 'mobile'"
    class="flex justify-center lg:hidden"
    aria-label="Баннер GG-HUB"
  >
    <a :href="mainSiteHref" target="_blank" rel="noopener noreferrer" class="inline-block">
      <img
        src="/images/journal-gg-hub-banner.png"
        alt="GG-HUB — управлять гильдией в MMORPG просто"
        width="512"
        height="512"
        :class="imgClass"
        loading="lazy"
        decoding="async"
      />
    </a>
  </aside>

  <aside v-else class="hidden lg:block" aria-label="Баннер GG-HUB">
    <div class="sticky top-20 flex h-[calc(100dvh-5rem)] items-center justify-center">
      <a :href="mainSiteHref" target="_blank" rel="noopener noreferrer" class="inline-block">
        <img
          src="/images/journal-gg-hub-banner.png"
          alt="GG-HUB — управлять гильдией в MMORPG просто"
          width="512"
          height="512"
          :class="imgClass"
          loading="lazy"
          decoding="async"
        />
      </a>
    </div>
  </aside>
</template>
