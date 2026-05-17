<script setup lang="ts">
defineProps<{
  tagline: string;
}>();

const authAsideImagePath = '/assets/images/3.webp';
</script>

<template>
  <div class="relative hidden overflow-hidden lg:block">
    <img
      :src="authAsideImagePath"
      alt=""
      width="1200"
      height="1600"
      loading="lazy"
      decoding="async"
      class="absolute inset-0 h-full w-full object-cover object-center"
      aria-hidden="true"
    />
    <div
      class="absolute inset-0 bg-gradient-to-t from-background/90 via-background/40 to-background/20"
      aria-hidden="true"
    />
    <div class="absolute inset-x-0 bottom-0 flex justify-center p-8 md:p-12">
      <p class="max-w-md text-center text-base text-foreground/90 drop-shadow-sm md:text-lg">
        {{ tagline }}
      </p>
    </div>
  </div>
</template>
