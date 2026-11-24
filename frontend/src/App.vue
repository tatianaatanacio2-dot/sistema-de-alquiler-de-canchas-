<template>
  <div
    v-if="sinConexion"
    class="offline-banner"
    role="status"
    aria-live="polite"
    >Sin conexión a internet. Comprueba tu red antes de guardar cambios.</div
  >
  <router-view />
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { session } from "@/services/api";

const router = useRouter();
const sinConexion = ref(!navigator.onLine);
const actualizarConexion = () => {
  sinConexion.value = !navigator.onLine;
};
onMounted(() => {
  window.addEventListener("online", actualizarConexion);
  window.addEventListener("offline", actualizarConexion);
});
onUnmounted(() => {
  window.removeEventListener("online", actualizarConexion);
  window.removeEventListener("offline", actualizarConexion);
});
watch(
  () => session.usuario,
  usuario => {
    if (!usuario && !router.currentRoute.value.meta.public)
      void router.replace("/ingresar");
  }
);
</script>

<style scoped>
.offline-banner {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 7000;
  padding: 12px 20px calc(12px + env(safe-area-inset-bottom));
  background: #7b2709;
  color: white;
  text-align: center;
}
</style>
