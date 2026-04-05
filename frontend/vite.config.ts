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

    const env = loadEnv(mode, process.cwd() + '/frontend');

    return {
        server: {
            host: '0.0.0.0',
            port: 3008,
            hmr: {
                host: mode === 'development' ? 'gg-hub.local' : 'gg-hub.local',
                protocol: 'ws',
                clientPort: mode === 'development' ? 80 : 3008
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
            }
        },
        plugins: [
            injectHomeSeoPlugin(mode),
            tailwindcss(),
            vue(),
            vueDevTools(),
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url))
            },
        },
        ssr: {
            noExternal: true,
        },
        build: {
            rollupOptions: ssrBuild
                ? undefined
                : {
                      output: {
                          manualChunks(id) {
                              if (id.includes('node_modules')) {
                                  return 'vendor';
                              }
                          },
                      },
                  },
            chunkSizeWarningLimit: 900,
        },
    }
});
