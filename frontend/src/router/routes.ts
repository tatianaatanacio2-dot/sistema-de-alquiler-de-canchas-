import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  { path: '/ingresar', component: () => import('@/pages/LoginPage.vue'), meta: { public: true } },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('@/pages/IndexPage.vue'), meta: { title: 'Resumen general' } },
      { path: 'cancha', component: () => import('@/pages/CanchaPage.vue'), meta: { title: 'Mi cancha' } },
      { path: 'clientes', component: () => import('@/pages/ClientesPage.vue'), meta: { title: 'Clientes' } },
      { path: 'reservas', component: () => import('@/pages/ReservasPage.vue'), meta: { title: 'Reservas' } },
    ],
  },
  { path: '/:pathMatch(.*)*', component: () => import('@/pages/ErrorNotFound.vue'), meta: { public: true } },
];

export default routes;
