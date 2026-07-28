<script setup lang="ts">
import { RouterLink } from 'vue-router';
import SiteLogo from '@/shared/ui/SiteLogo.vue';

defineProps<{
  tagline?: string;
}>();
</script>

<template>
  <section class="auth-page-layout">
    <RouterLink
      to="/"
      class="auth-page-layout__logo"
      aria-label="Перейти на главную страницу GG Hub"
    >
      <SiteLogo :size="52" />
    </RouterLink>

    <div class="auth-page-layout__story">
      <div class="auth-page-layout__copy">
        <h2>
          Твоя гильдия.<br>
          <span>Твоя команда.</span>
        </h2>

        <p>
          {{
            tagline
              || 'Общайся с игроками, находи гильдии и собирай команду для рейдов в любимых MMORPG.'
          }}
        </p>

        <div class="auth-page-layout__benefits">
          <div>
            <img
              class="auth-page-layout__benefit-icon"
              src="/assets/images/find_your_friends.webp"
              width="128"
              height="128"
              alt=""
              aria-hidden="true"
              decoding="async"
            >
            <b>Найди своих</b>
            <small>
              Игроков и гильдии<br>
              по интересам
            </small>
          </div>

          <div>
            <img
              class="auth-page-layout__benefit-icon"
              src="/assets/images/stay_in_the_know.webp"
              width="128"
              height="128"
              alt=""
              aria-hidden="true"
              decoding="async"
            >
            <b>Будь в курсе</b>
            <small>
              Новости, события<br>
              и уведомления
            </small>
          </div>

          <div>
            <img
              class="auth-page-layout__benefit-icon"
              src="/assets/images/play_together.webp"
              width="128"
              height="128"
              alt=""
              aria-hidden="true"
              decoding="async"
            >
            <b>Играй вместе</b>
            <small>
              Команды, рейды<br>
              и победы
            </small>
          </div>
        </div>
      </div>
    </div>

    <div class="auth-page-layout__content">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.auth-page-layout {
  position: relative;
  min-height: 100svh;
  padding: 0 clamp(2rem, 4.5vw, 5rem);
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(26rem, 31rem);
  gap: clamp(2rem, 4vw, 5rem);
  color: #f7f3ec;
  background:
    linear-gradient(
      90deg,
      rgba(2, 7, 10, 0.04),
      rgba(2, 7, 10, 0.12) 52%,
      rgba(2, 7, 10, 0.82)
    ),
    linear-gradient(0deg, rgba(2, 7, 10, 0.88), transparent 48%),
    url('/assets/images/auth-fantasy-party-v2.webp') center / cover no-repeat;
}

.auth-page-layout__logo {
  --foreground: #f7f3ec;

  position: absolute;
  z-index: 2;
  top: 1.5rem;
  left: clamp(2rem, 4.5vw, 5rem);
  display: inline-flex;
  border-radius: 0.35rem;
  filter: drop-shadow(0 2px 10px rgba(0, 0, 0, 0.55));
  transition: transform 180ms ease, opacity 180ms ease;
}

.auth-page-layout__logo:hover {
  opacity: 0.86;
  transform: scale(1.04);
}

.auth-page-layout__logo:focus-visible {
  outline: 2px solid rgba(255, 196, 46, 0.8);
  outline-offset: 4px;
}

.auth-page-layout__story {
  position: relative;
  min-width: 0;
}

.auth-page-layout__copy {
  position: absolute;
  bottom: clamp(3.5rem, 8vh, 6.5rem);
  width: min(100%, 48rem);
  text-shadow: 0 2px 15px #000;
}

.auth-page-layout__copy h2 {
  font-size: clamp(3.4rem, 4.25vw, 4.75rem);
  font-weight: 700;
  line-height: 0.98;
  letter-spacing: -0.04em;
  white-space: nowrap;
}

.auth-page-layout__copy h2 span {
  color: #f7b828;
}

.auth-page-layout__copy > p {
  max-width: 31rem;
  margin-top: 1.45rem;
  font-size: 1.12rem;
  line-height: 1.55;
}

.auth-page-layout__benefits {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  max-width: 40rem;
  margin-top: 2rem;
}

.auth-page-layout__benefits > div {
  text-align: center;
  border-left: 1px solid #c47d1a99;
}

.auth-page-layout__benefits > div:first-child {
  border: 0;
}

.auth-page-layout__benefit-icon {
  display: block;
  width: 4.6rem;
  height: 4.6rem;
  margin: 0 auto;
  object-fit: contain;
}

.auth-page-layout__benefits b,
.auth-page-layout__benefits small {
  display: block;
}

.auth-page-layout__benefits b {
  color: #ffc52d;
}

.auth-page-layout__benefits small {
  margin-top: 0.35rem;
}

.auth-page-layout__content {
  display: flex;
  align-items: center;
  justify-content: center;
}

@media (max-width: 1023px) {
  .auth-page-layout {
    display: flex;
    justify-content: center;
    padding: 5rem 1rem 1rem;
    background-position: 42% center;
  }

  .auth-page-layout__story {
    display: none;
  }

  .auth-page-layout__content {
    width: min(100%, 34rem);
  }
}
</style>
