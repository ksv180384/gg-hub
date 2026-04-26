import { createApp, createSSRApp } from 'vue';
import { createPinia } from 'pinia';
import { createWebHistory } from 'vue-router';
import App from './App.vue';
import { createRouterInstance } from './router';
import { setActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { setupHttpInterceptors } from '@/shared/api/http-interceptors';
import { setHydrating } from '@/ssr/hydrationFlag';
import '@/assets/main.css';

async function bootstrap() {
  const el = document.getElementById('app');
  const shouldHydrate = el !== null && el.children.length > 0;

  const pinia = createPinia();
  const router = createRouterInstance(createWebHistory(import.meta.env.BASE_URL));
  setActiveRouter(router);

  const app = shouldHydrate ? createSSRApp(App) : createApp(App);
  app.use(pinia);
  app.use(router);

  setupHttpInterceptors();

  const w = window as unknown as { __INITIAL_PINIA__?: Record<string, unknown> };
  const hasSsrState = w.__INITIAL_PINIA__ != null && typeof w.__INITIAL_PINIA__ === 'object';
  if (hasSsrState) {
    // SSR стейт уже сериализован в HTML как plain object — не делаем дорогое deep-clone на старте.
    pinia.state.value = w.__INITIAL_PINIA__ as typeof pinia.state.value;
    setHydrating(true);
  }

  const theme = useThemeStore(pinia);
  theme.init();

  await router.isReady();
  if (shouldHydrate) {
    app.mount('#app', true);
  } else {
    app.mount('#app');
  }

  setHydrating(false);

  // В SSR-гидрации router.beforeEach пропускается (isHydrating=true), поэтому для публичных страниц
  // нужно догрузить контекст/пользователя вручную после mount.
  const auth = useAuthStore(pinia);
  const siteContext = useSiteContextStore(pinia);
  queueMicrotask(() => {
    if (!siteContext.loading && !siteContext.data) {
      void siteContext.fetchContext();
    }
    if (!auth.loading && !auth.user) {
      void auth.fetchUser();
    }
  });
}

void bootstrap();
