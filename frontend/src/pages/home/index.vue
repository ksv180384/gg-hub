<script setup lang="ts">
import { computed } from 'vue';
import { useSiteContextStore } from '@/stores/siteContext';
import LandingPage from './LandingPage.vue';
import JournalView from './JournalView.vue';

const siteContext = useSiteContextStore();
const isGameSubdomain = computed(() => siteContext.isGameSubdomain);
</script>

<template>
  <JournalView v-if="isGameSubdomain" />
  <LandingPage v-else />
</template>
