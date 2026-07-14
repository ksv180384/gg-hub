<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
  <RouterLink
    to="/"
    class="auth-home-link"
    aria-label="Перейти на главную страницу"
  >
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.7"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
    >
      <path d="m3 11 9-8 9 8" />
      <path d="M5 10v10h14V10" />
      <path d="M9 20v-6h6v6" />
    </svg>

    <span>На главную</span>
  </RouterLink>
</template>

<style scoped>
.auth-home-link {
  display: flex;
  width: fit-content;
  align-items: center;
  gap: 0.4rem;
  margin: 0.8rem auto 0;
  padding: 0.3rem 0.45rem;
  border-radius: 0.35rem;
  color: rgba(255, 255, 255, 0.62);
  font-size: 0.8rem;
  line-height: 1.2;
  text-decoration: none;
  transition:
    color 160ms ease,
    background-color 160ms ease,
    transform 160ms ease;
}

.auth-home-link svg {
  width: 1rem;
  height: 1rem;
  color: #d9a329;
}

.auth-home-link:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.05);
  transform: translateY(-1px);
}

.auth-home-link:focus-visible {
  color: #ffffff;
  outline: 2px solid rgba(255, 196, 46, 0.55);
  outline-offset: 2px;
}
</style>
