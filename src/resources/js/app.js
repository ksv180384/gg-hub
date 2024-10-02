import './bootstrap';
import '../css/app.css';
import 'primeicons/primeicons.css'

import { createSSRApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config';
import Lara from '@primevue/themes/lara';
import { createPinia } from 'pinia';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    return createSSRApp({ render: () => h(App, props) })
      .use(PrimeVue, {
        theme: {
          preset: Lara,
          options: {
            default: 'info',
            severity: 'info'
          }
        },
      })
      .use(plugin)
      .use(ZiggyVue)
      .use(createPinia())
      .mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});
