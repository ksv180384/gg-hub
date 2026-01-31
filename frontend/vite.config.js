import { fileURLToPath, URL } from 'node:url';

import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import vueDevTools from 'vite-plugin-vue-devtools';
import tailwindcss from '@tailwindcss/vite';
import Components from 'unplugin-vue-components/vite';
import {PrimeVueResolver} from '@primevue/auto-import-resolver';

// https://vite.dev/config/
export default defineConfig(({ mode }) => {

  console.log(mode);

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
      tailwindcss(),
      vue(),
      vueDevTools(),
      Components({
        resolvers: [
          PrimeVueResolver()
        ]
      }),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
  }
});
