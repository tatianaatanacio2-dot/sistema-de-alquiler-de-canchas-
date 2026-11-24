<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div
        ><span class="eyebrow">RESERVAS Y COBROS DEL CLIENTE</span
        ><h1>Historial del cliente</h1></div
      >
      <q-btn
        flat
        no-caps
        color="primary"
        icon="arrow_back"
        label="Volver a clientes"
        to="/clientes"
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
    <template v-if="datos">
      <section class="panel q-pa-lg q-mb-lg">
        <h2>{{
          datos.cliente.nombre ||
          datos.cliente.dni ||
          "Cliente #" + datos.cliente.id
        }}</h2>
        <div class="row q-col-gutter-lg q-mt-xs text-grey-7">
          <div>DNI {{ datos.cliente.dni || "—" }}</div
          ><div>Teléfono {{ datos.cliente.telefono || "—" }}</div
          ><div>Correo {{ datos.cliente.correo || "—" }}</div>
        </div>
      </section>
      <div class="stats-grid">
        <div class="stat-card"
          ><span>Reservas</span><strong>{{ datos.reservas.length }}</strong
          ><small>Historial completo</small></div
        >
        <div class="stat-card"
          ><span>Completadas</span
          ><strong>{{
            datos.reservas.filter(item => item.estado === "completada").length
          }}</strong
          ><small>Alquileres finalizados</small></div
        >
        <div class="stat-card"
          ><span>Abonado neto</span
          ><strong class="importe">{{ dinero(abonado) }}</strong
          ><small>Después de devoluciones</small></div
        >
        <div class="stat-card"
          ><span>Saldo por cobrar</span
          ><strong class="importe">{{ dinero(pendiente) }}</strong
          ><small>Reservas vigentes y completadas</small></div
        >
      </div>
      <section class="panel">
        <q-table
          flat
          class="data-table"
          :rows="datos.reservas"
          :columns="columnas"
          row-key="id"
          :pagination="{ rowsPerPage: 10 }"
          no-data-label="Este cliente todavía no tiene reservas"
        >
          <template #body-cell-estado="props"
            ><q-td :props="props"
              ><q-badge
                outline
                :color="colores[props.row.estado as Estado]"
                :label="estados[props.row.estado as Estado]" /></q-td
          ></template>
          <template #body-cell-cobro="props"
            ><q-td :props="props"
              ><q-badge
                outline
                :color="estadoPago(props.row).color"
                :label="estadoPago(props.row).label" /></q-td
          ></template>
          <template #body-cell-acciones="props"
            ><q-td :props="props">
              <q-btn
                flat
                dense
                no-caps
                color="primary"
                label="Pagos"
                icon="payments"
                :to="{
                  path: '/pagos',
                  query: { reserva: String(props.row.id) }
                }"
              />
              <q-btn
                flat
                dense
                no-caps
                color="primary"
                label="Constancia"
                icon="print"
                :to="'/reservas/' + props.row.id + '/constancia'"
              /> </q-td
          ></template>
        </q-table>
      </section>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import type { QTableColumn } from "quasar";
import {
  api,
  colores,
  dinero,
  estados,
  horario,
  type Cliente,
  type Estado,
  type Reserva
} from "@/services/api";
import { estadoPago, saldoReserva } from "@/services/finanzas";

type HistorialCliente = { cliente: Cliente; reservas: Reserva[] };
const route = useRoute();
const datos = ref<HistorialCliente>();
const cargando = ref(false);
const error = ref("");
const abonado = computed(
  () =>
    datos.value?.reservas.reduce(
      (total, item) => total + Number(item.abonado),
      0
    ) || 0
);
const pendiente = computed(
  () =>
    datos.value?.reservas
      .filter(item => item.estado !== "cancelada")
      .reduce((total, item) => total + saldoReserva(item), 0) || 0
);
const columnas: QTableColumn<Reserva>[] = [
  {
    name: "id",
    label: "Reserva",
    field: "id",
    format: id => "#" + id,
    align: "left"
  },
  {
    name: "inicio",
    label: "Inicio",
    field: "inicio",
    format: horario,
    align: "left"
  },
  {
    name: "total",
    label: "Total",
    field: "total",
    format: dinero,
    align: "right"
  },
  {
    name: "abonado",
    label: "Abonado neto",
    field: "abonado",
    format: dinero,
    align: "right"
  },
  { name: "estado", label: "Reserva", field: "estado", align: "left" },
  {
    name: "cobro",
    label: "Pago",
    field: item => estadoPago(item).label,
    align: "left"
  },
  { name: "acciones", label: "Acciones", field: "id", align: "right" }
];
let secuencia = 0;
async function cargar() {
  const actual = ++secuencia;
  datos.value = undefined;
  error.value = "";
  cargando.value = true;
  try {
    const resultado = await api<HistorialCliente>(
      "/clientes/" + encodeURIComponent(String(route.params.id)) + "/historial"
    );
    if (actual === secuencia) datos.value = resultado;
  } catch (problema) {
    if (actual === secuencia) error.value = (problema as Error).message;
  } finally {
    if (actual === secuencia) cargando.value = false;
  }
}
watch(() => route.params.id, cargar, { immediate: true });
</script>

<style scoped>
.importe {
  font-size: clamp(20px, 2vw, 30px);
  overflow-wrap: anywhere;
}
</style>
