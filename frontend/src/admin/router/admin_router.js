
const routes = [
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/admin/view/index/Index.vue'),
    meta: {
      page_title: 'Панель администратора',
    },
  },
];

export default routes;
