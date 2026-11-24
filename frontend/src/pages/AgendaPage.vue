<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div
        ><span class="eyebrow">PLANIFICA EL PRÓXIMO PARTIDO</span
        ><h1>Agenda y disponibilidad</h1
        ><p
          >Consulta los intervalos libres y prepara una reserva con su
          horario.</p
        ></div
      >
      <q-btn
        color="primary"
        unelevated
        no-caps
        label="Nueva reserva"
        icon="add"
        :to="{ path: '/reservas', query: { nueva: '1', cancha: canchaId } }"
      />
    </div>
    <div class="row items-center q-gutter-sm q-mb-lg">
      <q-select
        v-model="canchaId"
        :options="canchas"
        option-value="id"
        option-label="nombre"
        emit-value
        map-options
        outlined
        dense
        label="Cancha"
        style="min-width: 160px"
      />
      <q-btn
        flat
        round
        icon="chevron_left"
        aria-label="Día anterior"
        @click="mover(-1)"
      />
      <q-input
        v-model="fecha"
        outlined
        dense
        stack-label
        type="date"
        label="Día de la agenda"
      />
      <q-btn
        flat
        round
        icon="chevron_right"
        aria-label="Día siguiente"
        @click="mover(1)"
      />
      <q-btn
        flat
        no-caps
        label="Hoy"
        color="primary"
        @click="fecha = fechaLocal()"
      />
      <q-btn
        flat
        round
        icon="refresh"
        aria-label="Actualizar disponibilidad"
        @click="cargar"
      />
    </div>
    <q-banner
      v-if="error"
      rounded
      class="bg-red-1 text-red-9 q-mb-lg"
      role="alert"
      >{{ error }}</q-banner
    >
    <q-inner-loading :showing="cargando" />
    <q-banner
      v-if="datos && !datos.disponible"
      class="bg-orange-1 text-orange-10 q-mb-lg"
      rounded
      >La cancha está inactiva o necesita una tarifa para recibir
      reservas.</q-banner
    >
    <div v-if="datos" class="dashboard-grid">
      <section class="panel">
        <div class="panel-heading"
          ><div
            ><h2>Horarios ocupados</h2
            ><p>Las reservas canceladas liberan su horario.</p></div
          ><q-badge color="primary" :label="datos.ocupados.length"
        /></div>
        <q-list v-if="datos.ocupados.length" separator>
          <q-item
            v-for="reserva in datos.ocupados"
            :key="reserva.id"
            class="request-row"
          >
            <q-item-section avatar
              ><q-icon name="event_busy" color="orange-8"
            /></q-item-section>
            <q-item-section
              ><q-item-label>Reserva #{{ reserva.id }}</q-item-label
              ><q-item-label caption
                >{{ horario(reserva.inicio) }} —
                {{ horario(reserva.fin) }}</q-item-label
              ></q-item-section
            >
            <q-item-section side
              ><q-badge
                outline
                :color="reserva.estado === 'completada' ? 'green-9' : 'primary'"
                >{{
                  reserva.estado === "completada" ? "Completada" : "Confirmada"
                }}</q-badge
              ></q-item-section
            >
          </q-item>
        </q-list>
        <div v-else class="empty-state"
          ><q-icon name="event_available" size="42px" /><h3
            >Sin reservas para este día</h3
          ></div
        >
      </section>
      <section class="panel">
        <div class="panel-heading"
          ><div
            ><h2>Intervalos libres</h2
            ><p
              >Hora de Bogotá. Se muestran horarios a partir del momento
              actual.</p
            ></div
          ></div
        >
        <q-list v-if="datos.libres.length" separator>
          <q-item
            v-for="libre in datos.libres"
            :key="libre.inicio"
            class="request-row"
          >
            <q-item-section avatar
              ><q-icon name="check_circle_outline" color="green-8"
            /></q-item-section>
            <q-item-section
              ><q-item-label
                >{{ libre.inicio.slice(11) }} —
                {{
                  libre.fin.slice(0, 10) === fecha
                    ? libre.fin.slice(11)
                    : "24:00"
                }}</q-item-label
              ><q-item-label caption
                >Horario disponible</q-item-label
              ></q-item-section
            >
            <q-item-section side
              ><q-btn
                flat
                no-caps
                color="primary"
                label="Reservar"
                @click="reservar(libre)"
            /></q-item-section>
          </q-item>
        </q-list>
        <div v-else class="empty-state"
          ><q-icon name="schedule" size="42px" /><h3
            >Sin intervalos disponibles</h3
          ><p>Consulta otra fecha.</p></div
        >
        <div class="q-px-lg q-pb-md text-caption text-grey-7"
          >Al seleccionar un intervalo se propone una hora de alquiler o el
          tiempo restante, si es menor.</div
        >
      </section>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { api, horario, type Cancha } from "@/services/api";
import { fechaLocal } from "@/services/finanzas";

type Intervalo = { inicio: string; fin: string };
type Disponibilidad = {
  fecha: string;
  disponible: boolean;
  libres: Intervalo[];
  ocupados: (Intervalo & { id: number; estado: string })[];
};
const router = useRouter();
const fecha = ref(fechaLocal());
const canchas = ref<Cancha[]>([]);
const canchaId = ref(1);
const datos = ref<Disponibilidad>();
const error = ref("");
const cargando = ref(false);
let secuencia = 0;
async function cargar() {
  const actual = ++secuencia;
  datos.value = undefined;
  if (!fecha.value) {
    datos.value = undefined;
    cargando.value = false;
    return;
  }
  cargando.value = true;
  error.value = "";
  try {
    const filtros = new URLSearchParams({
      fecha: fecha.value,
      cancha_id: String(canchaId.value)
    });
    const resultado = await api<Disponibilidad>("/disponibilidad?" + filtros);
    if (actual === secuencia) datos.value = resultado;
  } catch (problema) {
    if (actual === secuencia) error.value = (problema as Error).message;
  } finally {
    if (actual === secuencia) cargando.value = false;
  }
}
function mover(dias: number) {
  if (!fecha.value) {
    fecha.value = fechaLocal();
    return;
  }
  const dia = new Date(fecha.value + "T12:00:00Z");
  dia.setUTCDate(dia.getUTCDate() + dias);
  fecha.value = dia.toISOString().slice(0, 10);
}
function reservar(intervalo: Intervalo) {
  const fin = new Date(
    Math.min(
      Date.parse(intervalo.inicio + "Z") + 3600000,
      Date.parse(intervalo.fin + "Z")
    )
  )
    .toISOString()
    .slice(0, 16);
  void router.push({
    path: "/reservas",
    query: { nueva: "1", cancha: canchaId.value, inicio: intervalo.inicio, fin }
  });
}
watch([fecha, canchaId], cargar);
onMounted(async () => {
  await cargar();
  try {
    canchas.value = await api<Cancha[]>("/canchas");
  } catch (problema) {
    error.value = (problema as Error).message;
  }
});
</script>
