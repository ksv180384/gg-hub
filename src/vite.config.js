import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import { fileURLToPath, URL } from "node:url";

export default defineConfig({
  server: {
    host: true,
    port: 3004,
    hmr: {
      host: 'localhost',
    },
  },
  plugins: [
    laravel({
      input: 'resources/js/app.js',
      ssr: 'resources/js/ssr.js',
      refresh: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    Components({
      resolvers: [
        PrimeVueResolver()
      ]
    }),
    {
      name: 'vite-plugin-tailwindcss',
      enforce: 'post',
      apply: 'build',
      config: () => ({
        css: {
          postcss: {
            plugins: [tailwindcss(), autoprefixer()],
          },
        },
      }),
    },
  ],
  resolve: {
    alias: {
      // vue: 'vue/dist/vue.esm-bundler.js',
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
});
