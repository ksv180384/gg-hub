import { createApp, createSSRApp } from 'vue';
import { createPinia } from 'pinia';
import { createWebHistory } from 'vue-router';
import App from './App.vue';
import { createRouterInstance } from './router';
import { setActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';
import { setupHttpInterceptors } from '@/shared/api/http-interceptors';
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
  if (w.__INITIAL_PINIA__ != null && typeof w.__INITIAL_PINIA__ === 'object') {
    pinia.state.value = JSON.parse(JSON.stringify(w.__INITIAL_PINIA__)) as typeof pinia.state.value;
  }

  const theme = useThemeStore(pinia);
  theme.init();

  await router.isReady();
  if (shouldHydrate) {
    app.mount('#app', true);
  } else {
    app.mount('#app');
  }
}

void bootstrap();
