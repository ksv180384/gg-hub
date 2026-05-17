<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useRoute } from 'vue-router';
import NotFoundPage from '@/pages/not-found/index.vue';
import { useGuildAuctionRoulette } from '@/features/guild-auction-roulette';
import RouletteWheelCard from './RouletteWheelCard.vue';
import RouletteParticipantsCard from './RouletteParticipantsCard.vue';

const route = useRoute();
const guildId = computed(() => Number(route.params.id));

/**
 * `reactive()` даёт ref-unwrapping для свойств объекта,
 * чтобы вниз не прокидывались `Ref<...>` и template читал значения напрямую.
 */
const model = reactive(useGuildAuctionRoulette(guildId));
</script>

<template>
  <NotFoundPage v-if="model.guildAuctionAccessNotFound" />
  <div v-else class="container py-6">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
      <RouletteWheelCard :model="model" />
      <RouletteParticipantsCard :model="model" />
    </div>
  </div>
</template>
