import '@/assets/main.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/lara';

import App from '@/app/App.vue';
import router from '@/router/main_router.js';

import 'primeicons/primeicons.css';
// import 'primevue/resources/themes/lara-light-blue/theme.css'; // Синяя тема

const app = createApp(App);
// const app = createApp();
app.use(PrimeVue, {
    theme: {
        preset: {
            ...Aura,
            semantic:{
                ...Aura.semantic,
                primary: {
                    50: '{sky.50}',
                    100: '{sky.100}',
                    200: '{sky.200}',
                    300: '{sky.300}',
                    400: '{sky.400}',
                    500: '{sky.500}',
                    600: '{sky.600}',
                    700: '{sky.700}',
                    800: '{sky.800}',
                    900: '{sky.900}',
                    950: '{sky.950}'
                }
            }
        },
    },
});
app.use(createPinia());
app.use(router);

export { app };
