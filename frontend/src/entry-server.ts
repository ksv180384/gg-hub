import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { createPinia } from 'pinia';
import { createMemoryHistory } from 'vue-router';
import App from './App.vue';
import { createRouterInstance } from './router';
import { setActiveRouter } from '@/router/activeRouter';
import { useThemeStore } from '@/stores/theme';
import { setupSsrHttpInterceptors } from '@/shared/api/http-interceptors-ssr';
import { ssrRequestContext } from '@/ssr/requestContext';
import '@/assets/main.css';

let ssrInterceptorsInstalled = false;

export interface SsrRenderOptions {
  cookie?: string;
  host?: string;
}

export interface SsrRenderResult {
  html: string;
  piniaState: Record<string, unknown>;
}

/**
 * Рендер приложения на сервере (вызов внутри AsyncLocalStorage с cookie/host).
 */
export async function render(url: string, opts: SsrRenderOptions): Promise<SsrRenderResult> {
  if (!ssrInterceptorsInstalled) {
    setupSsrHttpInterceptors();
    ssrInterceptorsInstalled = true;
  }

  return ssrRequestContext.run({ cookie: opts.cookie, host: opts.host }, async () => {
    const pinia = createPinia();
    const router = createRouterInstance(createMemoryHistory(import.meta.env.BASE_URL));
    setActiveRouter(router);

    const app = createSSRApp(App);
    app.use(pinia);
    app.use(router);

    const theme = useThemeStore(pinia);
    theme.init();

    await router.push(url);
    await router.isReady();

    const html = await renderToString(app);
    const piniaState = pinia.state.value as Record<string, unknown>;

    setActiveRouter(null);

    return { html, piniaState };
  });
}
