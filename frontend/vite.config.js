import { fileURLToPath, URL } from 'node:url';

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import vueDevTools from 'vite-plugin-vue-devtools';
import tailwindcss from '@tailwindcss/vite';

// https://vite.dev/config/
export default defineConfig({
  server: {
    host: '0.0.0.0',
    port: 3008,
    hmr: {
      host: 'gg-hub.ru',
      clientPort: 80
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
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
})
