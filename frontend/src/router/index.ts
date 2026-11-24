import { defineRouter } from "#q-app";
import { createRouter, createWebHashHistory } from "vue-router";
import { cargarSesion, session } from "@/services/api";
import routes from "./routes";

export default defineRouter(() => {
  const router = createRouter({
    history: createWebHashHistory(),
    routes,
    scrollBehavior: (_to, _from, posicion) => posicion ?? { top: 0 }
  });
  router.beforeEach(async to => {
    if (to.path === "/horarios") return;
    if (!session.lista) await cargarSesion();
    if (!to.meta.public && !session.usuario) return "/ingresar";
    if (
      session.usuario?.rol === "cliente" &&
      !to.meta.public &&
      to.path !== "/mi-cuenta"
    )
      return "/mi-cuenta";
    if (to.path === "/ingresar" && session.usuario)
      return session.usuario.rol === "cliente" ? "/mi-cuenta" : "/";
  });
  return router;
});
