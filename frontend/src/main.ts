import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useThemeStore } from '@/stores/theme';

import '@/assets/main.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Тема: применить сохранённую или системную до первого рендера
const theme = useThemeStore();
theme.init();

// Контекст субдомена (admin / игра по слагу)
const siteContext = useSiteContextStore();
siteContext.fetchContext();

// Загружаем пользователя при старте (если есть сессия)
const auth = useAuthStore();
auth.fetchUser();

app.mount('#app');
