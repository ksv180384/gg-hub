import { createRouter, createWebHistory } from 'vue-router';
import MainLayout from '@/app/layouts/MainLayout.vue';
import { PERMISSION_ACCESS_ADMIN } from '@/shared/api/authApi';
import { useAuthStore } from '@/stores/auth';
import { useSiteContextStore } from '@/stores/siteContext';
import { useRouteLoadingStore } from '@/stores/routeLoading';

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
        {
          path: 'applications',
          name: 'user-applications',
          component: () => import('@/pages/applications/index.vue'),
          meta: { requiresAuth: true, title: 'Мои заявки и приглашения' },
        },
        {
          path: 'guilds/:id',
          name: 'guild-show',
          component: () => import('@/pages/guilds/[id]/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'guilds/:id/roster',
          name: 'guild-roster',
          component: () => import('@/pages/guilds/[id]/roster/index.vue'),
          meta: { requiresAuth: true, title: 'Состав гильдии' },
        },
        {
          path: 'guilds/:id/roster/:characterId',
          name: 'guild-roster-member',
          component: () => import('@/pages/guilds/[id]/roster/[characterId].vue'),
          meta: { requiresAuth: true, title: 'Участник гильдии' },
        },
        {
          path: 'guilds/:id/raids',
          name: 'guild-raids',
          component: () => import('@/pages/guilds/[id]/raids/index.vue'),
          meta: { requiresAuth: true, title: 'Рейды | Группы | КП' },
        },
        {
          path: 'guilds/:id/application-form',
          name: 'guild-application-form',
          component: () => import('@/pages/guilds/[id]/application-form/index.vue'),
          meta: { requiresAuth: false, title: 'Подать заявку в гильдию' },
        },
        {
          path: 'guilds/:id/applications',
          name: 'guild-applications',
          component: () => import('@/pages/guilds/[id]/applications/index.vue'),
          meta: { requiresAuth: true, title: 'Заявки и приглашения' },
        },
        {
          path: 'guilds/:id/applications/my/:applicationId',
          name: 'guild-application-my',
          component: () => import('@/pages/guilds/[id]/applications/my/[applicationId].vue'),
          meta: { requiresAuth: true, title: 'Моя заявка в гильдию' },
        },
        {
          path: 'guilds/:id/applications/list/:applicationId',
          name: 'guild-application-show',
          component: () => import('@/pages/guilds/[id]/applications/[applicationId].vue'),
          meta: { requiresAuth: true, title: 'Заявка в гильдию' },
        },
        {
          path: 'guilds/:id/calendar',
          name: 'guild-calendar',
          component: () => import('@/pages/guilds/[id]/calendar.vue'),
          meta: { requiresAuth: true, title: 'Календарь событий' },
        },
        {
          path: 'guilds/:id/events',
          name: 'guild-events',
          component: () => import('@/pages/guilds/[id]/events.vue'),
          meta: { requiresAuth: true, title: 'События' },
        },
        {
          path: 'guilds/:id/events/:eventHistoryId',
          name: 'guild-events-show',
          component: () => import('@/pages/guilds/[id]/events-show.vue'),
          meta: { requiresAuth: true, title: 'Событие' },
        },
        {
          path: 'guilds/:id/events/create',
          name: 'guild-events-create',
          component: () => import('@/pages/guilds/[id]/events-form.vue'),
          meta: { requiresAuth: true, title: 'Новое событие' },
        },
        {
          path: 'guilds/:id/events/:eventHistoryId/edit',
          name: 'guild-events-edit',
          component: () => import('@/pages/guilds/[id]/events-form.vue'),
          meta: { requiresAuth: true, title: 'Редактирование события' },
        },
        {
          path: 'guilds/:id/polls',
          name: 'guild-polls',
          component: () => import('@/pages/guilds/[id]/_placeholder.vue'),
          meta: { requiresAuth: true, title: 'Голосования' },
        },
        {
          path: 'guilds/:id/auction',
          name: 'guild-auction',
          component: () => import('@/pages/guilds/[id]/_placeholder.vue'),
          meta: { requiresAuth: true, title: 'Аукцион' },
        },
        {
          path: 'guilds/:id/roles',
          name: 'guild-roles',
          component: () => import('@/pages/guilds/[id]/roles/index.vue'),
          meta: { requiresAuth: true, title: 'Роли членов гильдии' },
        },
        {
          path: 'guilds/create',
          name: 'guilds-create',
          component: () => import('@/pages/guilds/create/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'guilds/:id/settings',
          name: 'guild-settings',
          component: () => import('@/pages/guilds/settings/index.vue'),
          meta: { requiresAuth: true },
        },
        { path: 'games', name: 'games', component: () => import('@/pages/games/index.vue') },
        {
          path: 'profile',
          name: 'profile',
          component: () => import('@/pages/profile/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'my-posts',
          name: 'my-posts',
          component: () => import('@/pages/my-posts/index.vue'),
          meta: { requiresAuth: true, title: 'Мои посты' },
        },
        {
          path: 'my-posts/create',
          name: 'my-posts-create',
          component: () => import('@/pages/my-posts/create/index.vue'),
          meta: { requiresAuth: true, title: 'Новый пост' },
        },
        {
          path: 'my-posts/:id/edit',
          name: 'my-posts-edit',
          component: () => import('@/pages/my-posts/[id]/edit.vue'),
          meta: { requiresAuth: true, title: 'Редактирование поста' },
        },
        {
          path: 'change-password',
          redirect: { name: 'profile' },
        },
        {
          path: 'characters',
          name: 'game-characters',
          component: () => import('@/pages/characters/game-list.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'characters/:id',
          name: 'game-character-show',
          component: () => import('@/pages/characters/[id]/show.vue'),
          meta: { requiresAuth: true },
        },
        { path: 'my-characters', name: 'my-characters', component: () => import('@/pages/characters/index.vue') },
        {
          path: 'my-characters/create',
          name: 'my-characters-create',
          component: () => import('@/pages/characters/create/index.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'my-characters/:id/edit',
          name: 'my-characters-edit',
          component: () => import('@/pages/characters/[id]/edit.vue'),
          meta: { requiresAuth: true },
        },
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
          path: 'admin/tags',
          name: 'admin-tags',
          component: () => import('@/pages/admin/tags/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/tags/create',
          name: 'admin-tags-create',
          component: () => import('@/pages/admin/tags/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/tags/:id/edit',
          name: 'admin-tags-edit',
          component: () => import('@/pages/admin/tags/edit.vue'),
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
          path: 'admin/guild-permissions',
          name: 'admin-guild-permissions',
          component: () => import('@/pages/admin/guild-permissions/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/guild-permissions/create',
          name: 'admin-guild-permissions-create',
          component: () => import('@/pages/admin/guild-permissions/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/guild-permissions/:id/edit',
          name: 'admin-guild-permissions-edit',
          component: () => import('@/pages/admin/guild-permissions/edit.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/guild-permission-groups',
          name: 'admin-guild-permission-groups',
          component: () => import('@/pages/admin/guild-permission-groups/index.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/guild-permission-groups/create',
          name: 'admin-guild-permission-groups-create',
          component: () => import('@/pages/admin/guild-permission-groups/create.vue'),
          meta: { requiresAuth: true, permission: PERMISSION_ACCESS_ADMIN },
        },
        {
          path: 'admin/guild-permission-groups/:id/edit',
          name: 'admin-guild-permission-groups-edit',
          component: () => import('@/pages/admin/guild-permission-groups/edit.vue'),
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

router.beforeEach(async (to, from) => {
  const routeLoading = useRouteLoadingStore();
  // Не показывать полноэкранный прелоадер при смене только query (например, фильтр на странице гильдий)
  const queryOnlyChange = from.name === to.name && from.path === to.path;
  if (!queryOnlyChange) {
    routeLoading.setLoading(true);
  }
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

router.afterEach(() => {
  useRouteLoadingStore().setLoading(false);
});

export default router;
