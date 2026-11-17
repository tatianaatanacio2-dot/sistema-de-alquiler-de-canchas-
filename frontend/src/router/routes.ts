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
      { path: 'clientes/:id/historial', component: () => import('@/pages/ClienteHistorialPage.vue'), meta: { title: 'Historial del cliente' } },
      { path: 'reservas/:id/constancia', component: () => import('@/pages/ConstanciaPage.vue'), meta: { title: 'Constancia de reserva' } },
      { path: 'lista-espera', component: () => import('@/pages/ListaEsperaPage.vue'), meta: { title: 'Lista de espera' } },
      { path: 'reservas', component: () => import('@/pages/ReservasPage.vue'), meta: { title: 'Reservas' } },
      { path: 'agenda', component: () => import('@/pages/AgendaPage.vue'), meta: { title: 'Agenda y disponibilidad' } },
      { path: 'pagos', component: () => import('@/pages/PagosPage.vue'), meta: { title: 'Pagos' } },
      { path: 'caja', component: () => import('@/pages/CajaPage.vue'), meta: { title: 'Caja diaria' } },
      { path: 'historial', component: () => import('@/pages/HistorialPage.vue'), meta: { title: 'Historial de cambios' } },
      { path: 'reportes', component: () => import('@/pages/ReportesPage.vue'), meta: { title: 'Reportes' } },
    ],
  },
  { path: '/:pathMatch(.*)*', component: () => import('@/pages/ErrorNotFound.vue'), meta: { public: true } },
];

export default routes;
