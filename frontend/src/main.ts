import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useThemeStore } from '@/stores/theme';
import { setupHttpInterceptors } from '@/shared/api/http-interceptors';

import '@/assets/main.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

setupHttpInterceptors();

// Тема: применить сохранённую или системную до первого рендера
const theme = useThemeStore();
theme.init();

app.mount('#app');
