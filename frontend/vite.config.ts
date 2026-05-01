import path from 'node:path';
import { fileURLToPath, URL } from 'node:url';

import { defineConfig, loadEnv, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';
import vueDevTools from 'vite-plugin-vue-devtools';
import tailwindcss from '@tailwindcss/vite';
import {
    buildHomeNoscriptHtml,
    buildHomePageStaticHeadHtml,
    DEFAULT_PRODUCTION_ORIGIN,
    normalizeSiteOrigin,
} from './src/seo/homePageSeo';

function injectHomeSeoPlugin(mode: string): Plugin {
    const root = path.dirname(fileURLToPath(import.meta.url));
    return {
        name: 'gg-inject-home-seo',
        transformIndexHtml: {
            order: 'pre',
            handler(html, ctx) {
                const env = loadEnv(mode, root, '');
                const fallbackOrigin =
                    mode === 'development' ? 'http://gg-hub.local' : DEFAULT_PRODUCTION_ORIGIN;
                const siteOrigin = normalizeSiteOrigin(env.VITE_SITE_URL, fallbackOrigin);
                const seo = buildHomePageStaticHeadHtml(siteOrigin, env);
                const noscript = buildHomeNoscriptHtml();
                let out = html
                    .replace('<!--INJECT_HOME_SEO-->', seo)
                    .replace('<!--INJECT_HOME_NOSCRIPT-->', noscript);
                /* В dev (vite без SSR) подставляем null; в prod сборке оставляем маркер для Node SSR. */
                if (ctx.server) {
                    out = out.replace('<!--pinia-state-->', 'null');
                }
                return out;
            },
        },
    };
}

export default defineConfig(({ mode }) => {
    const ssrBuild = process.argv.includes('--ssr');
    const configRoot = path.dirname(fileURLToPath(import.meta.url));
    const env = loadEnv(mode, configRoot, '');

    return {
        server: {
            host: '0.0.0.0',
            port: 3008,
            /**
             * HMR WebSocket: server.mjs передаёт hmr.server (тот же http.Server, что Express).
             * clientPort — порт в браузере: за nginx (Docker) обычно 80; без nginx — VITE_HMR_CLIENT_PORT=3008.
             */
            hmr: {
                protocol: 'ws',
                clientPort:
                    Number(env.VITE_HMR_CLIENT_PORT || env.VITE_APP_HMR_PORT) ||
                    (mode === 'development' ? 80 : 3008),
            },
            watch: {
                usePolling: true
            },
            // Добавляем разрешенные хосты
            allowedHosts: [
                'localhost',
                'gg-hub.local',
                '.gg-hub.local', // Разрешаем все субдомены
            ],
            proxy: {
                '/api/v1': {
                    target: 'http://gg-nginx:80',
                    changeOrigin: true,
                    secure: false,
                    // logLevel: 'debug'
                },
                /** Socket.IO: в Docker задайте VITE_SOCKET_DEV_PROXY_TARGET=http://socket-server-nodejs:3007 */
                '/socket.io': {
                    target:
                        process.env.VITE_SOCKET_DEV_PROXY_TARGET || 'http://127.0.0.1:3007',
                    changeOrigin: true,
                    ws: true,
                },
            },
        },
        plugins: [
            injectHomeSeoPlugin(mode),
            tailwindcss(),
            vue(),
            /* На node server.mjs --dev отключаем: меньше full-reload, меньше гонок с ssrLoadModule (Vite 7+). */
            ...(process.env.GG_SSR_DEV_SERVER === '1' ? [] : [vueDevTools()]),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url)),
            },
        },
        ssr: {
            noExternal: [
                'vue',
                '@vue/server-renderer',
                '@vue/runtime-dom',
                '@vue/runtime-core',
                '@vue/reactivity',
                '@vue/shared',
                'vue-router',
                'pinia',
                'radix-vue',
                'radix-ui',
                'axios',
                '@tiptap/vue-3',
            ],
            resolve: {
                conditions: ['import', 'module', 'default'],
                externalConditions: ['node', 'import', 'module', 'default'],
            },
        },
        define: {
            __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: true,
        },
        build: {
            rollupOptions: ssrBuild
                ? undefined
                : {
                      output: {
                          manualChunks(id) {
                              if (!id.includes('node_modules')) return;

                              // Базовые фреймворк-зависимости всегда нужны.
                              if (
                                  id.includes('/vue/') ||
                                  id.includes('/@vue/') ||
                                  id.includes('/vue-router/') ||
                                  id.includes('/pinia/')
                              ) {
                                  return 'vue';
                              }

                              // UI (на главной может встречаться, но лучше отдельным чанком).
                              if (id.includes('/radix-vue/') || id.includes('/radix-ui/')) {
                                  return 'radix';
                              }

                              // Редактор (должен приезжать только на страницах создания/редактирования постов).
                              if (id.includes('/@tiptap/')) {
                                  return 'tiptap';
                              }

                              // Сокеты не нужны для лендинга.
                              if (id.includes('/socket.io-client/')) {
                                  return 'socket';
                              }

                              // Excel импортится динамически, но держим отдельным чанком на всякий случай.
                              if (id.includes('/exceljs/')) {
                                  return 'exceljs';
                              }

                              // Остальное — отдельные чанки по решению Rollup.
                          },
                      },
                  },
            chunkSizeWarningLimit: 900,
        },
    }
});
