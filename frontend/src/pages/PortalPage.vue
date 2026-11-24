<template>
  <q-layout view="hHh lpR fFf">
    <q-header class="topbar"
      ><q-toolbar>
        <q-icon name="sports_soccer" size="28px" class="q-mr-sm" />
        <q-toolbar-title>Mis reservas</q-toolbar-title>
        <q-btn
          flat
          icon="logout"
          label="Salir"
          no-caps
          @click="salir"
        /> </q-toolbar
    ></q-header>
    <q-page-container
      ><q-page padding class="portal-page">
        <h1 class="text-h5">Hola, {{ session.usuario?.nombre }}</h1>
        <p v-if="cancha"
          >{{ cancha.nombre }} · {{ dinero(cancha.tarifa || 0) }} por hora ·
          Hora de Perú</p
        >
        <q-banner
          v-if="error"
          class="bg-red-1 text-red-9 q-mb-md"
          role="alert"
          >{{ error }}</q-banner
        >
        <q-btn
          outline
          color="primary"
          label="Actualizar"
          icon="refresh"
          no-caps
          :loading="cargando"
          @click="cargar"
        />
        <q-card flat bordered class="q-mt-md q-pa-md">
          <h2 class="text-h6 q-mt-none">Reserva tu cancha</h2>
          <q-form @submit="reservar" class="q-gutter-md">
            <div class="row q-col-gutter-md">
              <q-input
                class="col-12 col-sm-6"
                v-model="inicio"
                type="datetime-local"
                outlined
                label="Inicio (Perú)"
                stack-label
                :rules="[v => !!v || 'Selecciona el inicio']"
              />
              <q-input
                class="col-12 col-sm-6"
                v-model="fin"
                type="datetime-local"
                outlined
                label="Fin (Perú)"
                stack-label
                :rules="[
                  v =>
                    (!!v && v > inicio) || 'El fin debe ser posterior al inicio'
                ]"
              />
            </div>
            <p
              >Consulta disponibilidad antes de confirmar. El importe definitivo
              se calcula al guardar.</p
            >
            <q-btn
              outline
              color="primary"
              no-caps
              label="Ver disponibilidad del día"
              :loading="consultando"
              :disable="!inicio"
              @click="disponibilidad"
            />
            <p v-if="libres !== null" role="status">{{ libres }}</p>
            <div
              ><q-btn
                type="submit"
                color="primary"
                label="Confirmar reserva"
                no-caps
                :loading="guardando"
                :disable="!cancha?.activa"
            /></div>
          </q-form>
        </q-card>
        <h2 class="text-h6">Historial de reservas</h2>
        <p v-if="!cargando && !reservas.length">Todavía no tienes reservas.</p>
        <q-card
          v-for="reserva in reservas"
          :key="reserva.id"
          flat
          bordered
          class="q-pa-md q-mb-sm"
        >
          <div class="row items-center justify-between q-gutter-sm">
            <div
              ><strong
                >#{{ reserva.id }} · {{ estados[reserva.estado] }}</strong
              >
              <p class="q-mb-xs"
                >{{ horario(reserva.inicio) }} — {{ horario(reserva.fin) }}</p
              >
              <span
                >Total {{ dinero(reserva.total) }} · Abonado
                {{ dinero(reserva.abonado) }}</span
              >
            </div>
            <q-btn
              v-if="reserva.estado === 'confirmada'"
              outline
              color="negative"
              no-caps
              label="Cancelar"
              :disable="guardando"
              @click="cancelar(reserva)"
            />
          </div>
          <p
            v-if="reserva.estado === 'cancelada' && Number(reserva.abonado) > 0"
            class="q-mb-none q-mt-sm"
            >Contacta con administración para gestionar tu devolución.</p
          >
        </q-card>
      </q-page></q-page-container
    >
  </q-layout>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useQuasar } from "quasar";
import {
  api,
  cerrarSesion,
  session,
  dinero,
  horario,
  estados,
  type Cancha,
  type Reserva
} from "@/services/api";
const router = useRouter();
const q = useQuasar();
const cancha = ref<Cancha>();
const reservas = ref<Reserva[]>([]);
const inicio = ref("");
const fin = ref("");
const libres = ref<string | null>(null);
const error = ref("");
const cargando = ref(false);
const guardando = ref(false);
const consultando = ref(false);
async function cargar() {
  if (cargando.value) return;
  cargando.value = true;
  error.value = "";
  try {
    [cancha.value, reservas.value] = await Promise.all([
      api<Cancha>("/portal/cancha"),
      api<Reserva[]>("/portal/reservas")
    ]);
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    cargando.value = false;
  }
}
async function disponibilidad() {
  if (consultando.value) return;
  consultando.value = true;
  libres.value = null;
  error.value = "";
  try {
    const resultado = await api<{ libres: { inicio: string; fin: string }[] }>(
      "/portal/disponibilidad?fecha=" + inicio.value.slice(0, 10)
    );
    libres.value = resultado.libres.length
      ? resultado.libres
          .map(v => horario(v.inicio) + " hasta " + horario(v.fin))
          .join(" · ")
      : "No hay horarios libres para ese día.";
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    consultando.value = false;
  }
}
async function reservar() {
  if (guardando.value) return;
  guardando.value = true;
  error.value = "";
  try {
    const reserva = await api<Reserva>("/portal/reservas", "POST", {
      inicio: inicio.value,
      fin: fin.value
    });
    q.notify({
      type: "positive",
      message: "Reserva confirmada por " + dinero(reserva.total)
    });
    await cargar();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    guardando.value = false;
  }
}
function cancelar(reserva: Reserva) {
  q.dialog({
    title: "Cancelar reserva #" + reserva.id,
    message:
      "¿Quieres cancelar este horario? Si abonaste un importe, administración gestionará la devolución.",
    cancel: true,
    persistent: true
  }).onOk(() => {
    void confirmarCancelacion(reserva.id);
  });
}
async function confirmarCancelacion(id: number) {
  if (guardando.value) return;
  guardando.value = true;
  error.value = "";
  try {
    await api("/portal/reservas/" + id + "/cancelar", "PATCH");
    await cargar();
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    guardando.value = false;
  }
}
async function salir() {
  try {
    await api("/portal/salir", "POST");
  } catch {
    /* La sesión local se elimina también cuando no hay conexión. */
  } finally {
    cerrarSesion();
    await router.replace("/ingresar");
  }
}
onMounted(cargar);
</script>

<style scoped>
.portal-page {
  max-width: 1000px;
  margin: auto;
  overflow-wrap: anywhere;
}
</style>
