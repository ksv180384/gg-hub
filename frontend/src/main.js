import './assets/main.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';

import App from './App.vue';
import main_router from './router/main_router.js';

import 'primeicons/primeicons.css';
// import 'primevue/resources/themes/aura-light-blue/theme.css'; // Синяя тема

const app = createApp(App);
// const app = createApp();
app.use(PrimeVue, {
  theme: {
    preset: Aura,
  }
});
app.use(createPinia());
app.use(main_router);

app.mount('#app')
