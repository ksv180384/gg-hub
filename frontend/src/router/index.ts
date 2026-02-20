import { createRouter, createWebHistory } from 'vue-router';
import MainLayout from '@/app/layouts/MainLayout.vue';
import { PERMISSION_ACCESS_ADMIN } from '@/shared/api/authApi';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';

declare module 'vue-router' {
  interface RouteMeta {
    /** Редирект на логин при 401 только если текущая страница для авторизованных */
    requiresAuth?: boolean;
    /** Требуемый permission (slug). Если у пользователя роль Admin — доступ разрешён ко всем (hasPermission в store). */
    permission?: string;
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
          path: 'profile',
          name: 'profile',
          component: () => import('@/pages/profile/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'change-password',
          redirect: { name: 'profile' },
        },
        { path: 'characters', name: 'characters', component: () => import('@/pages/characters/index.vue') },
        { path: 'guild', name: 'guild', component: () => import('@/pages/guild/index.vue') },
        {
          path: 'admin/users',
          name: 'admin-users',
          component: () => import('@/pages/admin/users/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/users/:id',
          name: 'admin-users-show',
          component: () => import('@/pages/admin/users/[id]/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/roles',
          name: 'admin-roles',
          component: () => import('@/pages/admin/roles/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permissions',
          name: 'admin-permissions',
          component: () => import('@/pages/admin/permissions/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permission-groups',
          name: 'admin-permission-groups',
          component: () => import('@/pages/admin/permission-groups/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permission-groups/create',
          name: 'admin-permission-groups-create',
          component: () => import('@/pages/admin/permission-groups/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permission-groups/:id/edit',
          name: 'admin-permission-groups-edit',
          component: () => import('@/pages/admin/permission-groups/edit.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permissions/create',
          name: 'admin-permissions-create',
          component: () => import('@/pages/admin/permissions/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/permissions/:id/edit',
          name: 'admin-permissions-edit',
          component: () => import('@/pages/admin/permissions/edit.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/roles/create',
          name: 'admin-roles-create',
          component: () => import('@/pages/admin/roles/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/roles/:id/edit',
          name: 'admin-roles-edit',
          component: () => import('@/pages/admin/roles/edit.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/games',
          name: 'admin-games',
          component: () => import('@/pages/admin/games/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/games/create',
          name: 'admin-games-create',
          component: () => import('@/pages/admin/games/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/games/:id/edit',
          name: 'admin-games-edit',
          component: () => import('@/pages/admin/games/edit.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
      ],
    },
    { path: '/login', name: 'login', component: () => import('@/pages/auth/login/index.vue') },
    { path: '/register', name: 'register', component: () => import('@/pages/auth/register/index.vue') },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/forgot-password/index.vue') },
    { path: '/reset-password', name: 'reset-password', component: () => import('@/pages/auth/reset-password/index.vue') },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  const siteContext = useSiteContextStore();
  await siteContext.fetchContext();
  await auth.fetchUser();

  // Админ-субдомен доступен только пользователям с правом «Администрирование» (access.admin).
  if (typeof window !== 'undefined') {
    const host = window.location.host;
    const isOnAdminSubdomain = host.startsWith('admin.');
    if (isOnAdminSubdomain && !auth.hasPermission(PERMISSION_ACCESS_ADMIN)) {
      const mainHost = host.replace(/^admin\./, '');
      window.location.href = `${window.location.protocol}//${mainHost}/`;
      return false;
    }
  }

  const isGuestRoute = to.name && guestRouteNames.includes(to.name as (typeof guestRouteNames)[number]);
  if (isGuestRoute) {
    if (auth.isAuthenticated) {
      return { path: '/', replace: true };
    }
  }
  const requiredPermission = to.meta.permission as string | undefined;
  if (requiredPermission && auth.isAuthenticated && !auth.hasPermission(requiredPermission)) {
    return { path: '/', replace: true };
  }
});

export default router;
