import { createRouter, createWebHistory } from 'vue-router';
import MainLayout from '@/app/layouts/MainLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

declare module 'vue-router' {
  interface RouteMeta {
    /** Редирект на логин при 401 только если текущая страница для авторизованных */
    requiresAuth?: boolean;
  }
}

const guestRouteNames = ['login', 'register', 'forgot-password', 'reset-password'] as const;

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
        { path: 'games', name: 'games', component: () => import('@/pages/games/index.vue') },
        {
          path: 'games/create',
          name: 'games-create',
          component: () => import('@/pages/games/create/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'games/:id/edit',
          name: 'games-edit',
          component: () => import('@/pages/games/edit/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'change-password',
          name: 'change-password',
          component: () => import('@/pages/auth/change-password/index.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    { path: '/login', name: 'login', component: () => import('@/pages/auth/login/index.vue') },
    { path: '/register', name: 'register', component: () => import('@/pages/auth/register/index.vue') },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/forgot-password/index.vue') },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/pages/auth/reset-password/index.vue') },
  ],
});

const adminOnlyRouteNames = ['games-create', 'games-edit'] as const;

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  const siteContext = useSiteContextStore();
  const isGuestRoute = to.name && guestRouteNames.includes(to.name as (typeof guestRouteNames)[number]);
  if (isGuestRoute) {
    // Не вызываем fetchUser() здесь — иначе при 401 интерцептор редиректит на login,
    // guard снова вызывает fetchUser() → бесконечный цикл. Загрузка пользователя только в main.ts.
    if (auth.isAuthenticated) {
      return { path: '/', replace: true };
    }
  }
  // Создание и редактирование игр — только с админского субдомена
  const isAdminOnlyRoute = to.name && adminOnlyRouteNames.includes(to.name as (typeof adminOnlyRouteNames)[number]);
  if (isAdminOnlyRoute && !siteContext.isAdmin) {
    return { path: '/games', replace: true };
  }
});

export default router;
