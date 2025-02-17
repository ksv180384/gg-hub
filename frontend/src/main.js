import './assets/main.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import main_router from './router/main_router.js';

const app = createApp(App);
// const app = createApp();

app.use(createPinia());
app.use(main_router);

app.mount('#app')
