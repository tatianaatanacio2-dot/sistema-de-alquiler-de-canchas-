import { defineConfig } from "#q-app";
import { normalizarServidor } from "./src/services/servidor";

export default defineConfig(({ mode }) => {
  const direccion = process.env.CANCHAS_SERVIDOR?.trim() || "";
  const publicacion = Boolean(
    mode.capacitor && process.env.CANCHAS_PUBLICACION === "1"
  );
  if (publicacion && !direccion)
    throw new Error("Configura CANCHAS_SERVIDOR para publicar.");
  let servidor = "";
  if (direccion) {
    try {
      servidor = normalizarServidor(direccion, !publicacion);
    } catch (problema) {
      throw new Error("Valor inválido para CANCHAS_SERVIDOR.", {
        cause: problema
      });
    }
  }

  return {
    css: ["app.scss"],
    extras: ["roboto-font", "material-icons"],
    build: {
      vueRouterMode: "hash",
      publicPath: "./",
      defineEnv: {
        CANCHAS_SERVIDOR: servidor,
        CANCHAS_PUBLICACION: publicacion
      }
    },
    devServer: {
      open: false,
      port: 9012,
      proxy: { "/api": { target: "http://127.0.0.1:8012", changeOrigin: true } }
    },
    framework: { lang: "es", plugins: ["Dialog", "Notify"] }
  };
});
