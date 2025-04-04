import { createRouter, createWebHistory } from 'vue-router';
import { get } from '@/services/api/query';

// Импортируем роутеры для админки и основного приложения
import appRoutes from '@/app/router/app_router.js';
import adminRoutes from '@/admin/router/admin_router.js';


const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: () => import('@/app/App.vue'),
      children: appRoutes,
    },
    {
      path: '/admin',
      component: () => import('@/admin/Admin.vue'),
      children: adminRoutes,
    },
  ],
});

router.beforeEach(async (to, from, next) => {

  document.title = `${to.meta.page_title}`;

  try {
    const res = await get(to.fullPath)
    console.log(res);
  } catch (e) {
    console.error(e);
  }


  next();


  // if (!(to.name === from.name && to.query?.open_login)){
  //   // Scroll page to top on every route change
  //   window.scrollTo({
  //     top: 0,
  //     left: 0,
  //     behavior: 'smooth',
  //   });
  // }
});

export default router;

