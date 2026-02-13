import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useAuthStore } from '@/stores/auth';

import '@/assets/main.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Загружаем пользователя при старте (если есть сессия)
const auth = useAuthStore();
auth.fetchUser();

app.mount('#app');
