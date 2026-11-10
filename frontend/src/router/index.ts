import { defineRouter } from '#q-app';
import { createRouter, createWebHashHistory } from 'vue-router';
import { cargarSesion, session } from '@/services/api';
import routes from './routes';

export default defineRouter(() => {
  const router = createRouter({ history: createWebHashHistory(), routes });
  router.beforeEach(async to => {
    if (!session.lista) await cargarSesion();
    if (!to.meta.public && !session.usuario) return '/ingresar';
    if (to.path === '/ingresar' && session.usuario) return '/';
  });
  return router;
});
