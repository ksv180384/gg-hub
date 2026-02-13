import { createRouter, createWebHistory } from 'vue-router';
import MainLayout from '@/app/layouts/MainLayout.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: MainLayout,
      children: [
        { path: '', name: 'home', component: () => import('@/pages/home/index.vue') },
        { path: 'news', name: 'news', component: () => import('@/pages/news/index.vue') },
        { path: 'guilds', name: 'guilds', component: () => import('@/pages/guilds/index.vue') },
        { path: 'change-password', name: 'change-password', component: () => import('@/pages/auth/change-password/index.vue') },
      ],
    },
    { path: '/login', name: 'login', component: () => import('@/pages/auth/login/index.vue') },
    { path: '/register', name: 'register', component: () => import('@/pages/auth/register/index.vue') },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/forgot-password/index.vue') },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/pages/auth/reset-password/index.vue') },
  ],
});

export default router;
