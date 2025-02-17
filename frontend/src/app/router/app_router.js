
const routes = [
  {
    path: '/',
    name: 'index',
    component: () => import('@/app/views/index/Index.vue'),
    meta: {
      page_title: 'Авторизация',
    },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/app/views/auth/Login.vue'),
    meta: {
      page_title: 'Авторизация',
    },
  },
];

export default routes;
