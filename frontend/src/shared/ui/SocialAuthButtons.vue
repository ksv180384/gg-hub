<script setup lang="ts">
const apiBase = (import.meta.env.VITE_API_URL ?? '/').replace(/\/+$/, '') || '';

function socialUrl(provider: string) {
  return `${apiBase}/auth/${provider}/redirect`;
}
</script>

<template>
  <div class="social-auth-buttons">
    <a
      :href="socialUrl('yandex')"
      class="social-auth-buttons__yandex"
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path d="M13.62 21.75h2.56V2.25h-3.78c-4.03 0-6.15 2.08-6.15 5.12 0 2.56 1.28 4.03 3.62 5.56l-4.1 8.82h2.72l4.47-9.56-1.2-.83c-1.83-1.28-2.74-2.35-2.74-4.2 0-1.88 1.28-3.22 3.44-3.22h.92l.24 19.81Z" />
      </svg>
      <span>Войти через Яндекс</span>
    </a>
  </div>
</template>

<style scoped>
.social-auth-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.social-auth-buttons__yandex {
  position: relative;
  display: flex;
  width: 100%;
  height: 3.55rem;
  align-items: center;
  justify-content: center;
  padding: 0 3.75rem;
  border: 1px solid rgba(235, 236, 237, 0.3);
  border-radius: 0.45rem;
  color: rgba(255, 255, 255, 0.88);
  background: rgba(255, 255, 255, 0.025);
  font-size: 1rem;
  font-weight: 400;
  line-height: 1;
  text-decoration: none;
  transition:
    border-color 150ms ease,
    background-color 150ms ease,
    color 150ms ease;
}

.social-auth-buttons__yandex svg {
  position: absolute;
  left: 1.25rem;
  width: 1.8rem;
  height: 1.8rem;
  fill: #fc3f1d;
}

.social-auth-buttons__yandex:hover {
  border-color: rgba(255, 255, 255, 0.48);
  color: #fff;
  background: rgba(255, 255, 255, 0.055);
}

.social-auth-buttons__yandex:focus-visible {
  outline: 2px solid rgba(255, 196, 46, 0.42);
  outline-offset: 2px;
}
</style>
