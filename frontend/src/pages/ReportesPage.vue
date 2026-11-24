<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div
        ><span class="eyebrow">SEGUIMIENTO DE RESERVAS</span><h1>Reportes</h1
        ><p
          >Reservas según su fecha de inicio y los saldos actuales de cada
          alquiler.</p
        ></div
      >
      <q-btn
        outline
        no-caps
        color="primary"
        icon="download"
        label="Descargar CSV"
        :disable="!datos || cargando"
        @click="descargar"
      />
    </div>
    <section class="panel q-mb-lg">
      <q-form class="row items-start q-gutter-md q-pa-lg" @submit="cargar">
        <q-input
          v-model="desde"
          outlined
          dense
          stack-label
          type="date"
          label="Desde"
          :rules="[v => !!v || 'Selecciona una fecha']"
        />
        <q-input
          v-model="hasta"
          outlined
          dense
          stack-label
          type="date"
          label="Hasta"
          :rules="[
            v =>
              (!!v && v >= desde) ||
              'El fin debe ser igual o posterior al inicio'
          ]"
        />
        <q-select
          v-model="estado"
          clearable
          outlined
          dense
          label="Estado de la reserva"
          emit-value
          map-options
          :options="opcionesEstado"
          style="min-width: 195px"
        />
        <q-btn
          color="primary"
          unelevated
          no-caps
          type="submit"
          label="Generar reporte"
          :loading="cargando"
        />
      </q-form>
      <p class="q-px-lg q-pb-md q-mb-none text-caption text-grey-7"
        >Selecciona un período de hasta 366 días. Los saldos incluyen los pagos
        y devoluciones registrados hasta este momento.</p
      >
    </section>
    <q-banner
      v-if="error"
      class="bg-red-1 text-red-9 q-mb-lg"
      rounded
      role="alert"
      >{{ error }}</q-banner
    >
    <template v-if="datos">
      <div class="stats-grid">
        <div v-for="tarjeta in tarjetas" :key="tarjeta.nombre" class="stat-card"
          ><span>{{ tarjeta.nombre }}</span
          ><strong class="importe">{{ tarjeta.valor }}</strong
          ><small>{{ tarjeta.nota }}</small></div
        >
      </div>
      <q-banner
        v-if="Number(datos.totales.porDevolver) > 0"
        rounded
        class="bg-orange-1 text-orange-10 q-mb-lg"
      >
        Las reservas canceladas de este reporte tienen
        {{ dinero(datos.totales.porDevolver) }} pendientes de devolver.
      </q-banner>
      <section class="panel">
        <div class="panel-heading"
          ><div
            ><h2>Detalle de reservas</h2
            ><p
              >{{ datos.desde }} al {{ datos.hasta }} ·
              {{
                datos.estado ? estados[datos.estado] : "Todos los estados"
              }}</p
            ></div
          ></div
        >
        <q-table
          flat
          class="data-table"
          :rows="datos.reservas"
          :columns="columnas"
          row-key="id"
          :pagination="{ rowsPerPage: 10 }"
          no-data-label="No hay reservas para los filtros seleccionados"
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
            ><q-td :props="props"
              ><q-btn
                flat
                dense
                no-caps
                color="primary"
                icon="print"
                label="Constancia"
                :to="'/reservas/' + props.row.id + '/constancia'" /></q-td
          ></template>
        </q-table>
      </section>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { exportFile, useQuasar, type QTableColumn } from "quasar";
import {
  api,
  colores,
  dinero,
  estados,
  horario,
  type Estado,
  type Reserva
} from "@/services/api";
import {
  estadoPago,
  fechaLocal,
  nombreMetodo,
  saldoReserva
} from "@/services/finanzas";

type Reporte = {
  desde: string;
  hasta: string;
  estado: Estado | null;
  reservas: Reserva[];
  totales: {
    programado: string;
    abonado: string;
    pendiente: string;
    porDevolver: string;
  };
};
const q = useQuasar();
const desde = ref(fechaLocal().slice(0, 8) + "01");
const hasta = ref(fechaLocal());
const estado = ref<Estado | null>(null);
const datos = ref<Reporte>();
const cargando = ref(false);
const error = ref("");
const opcionesEstado = Object.entries(estados).map(([value, label]) => ({
  value,
  label
}));
const tarjetas = computed(() =>
  datos.value
    ? [
        {
          nombre: "Reservas",
          valor: datos.value.reservas.length,
          nota: "Coinciden con los filtros"
        },
        {
          nombre: "Importe programado",
          valor: dinero(datos.value.totales.programado),
          nota: "Reservas vigentes y completadas"
        },
        {
          nombre: "Abonado neto",
          valor: dinero(datos.value.totales.abonado),
          nota: "Después de devoluciones"
        },
        {
          nombre: "Saldo por cobrar",
          valor: dinero(datos.value.totales.pendiente),
          nota: "Excluye reservas canceladas"
        }
      ]
    : []
);
const columnas: QTableColumn<Reserva>[] = [
  {
    name: "id",
    label: "Reserva",
    field: "id",
    align: "left",
    format: id => "#" + id
  },
  { name: "cliente", label: "Cliente", field: "cliente", align: "left" },
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
  {
    name: "saldo",
    label: "Saldo / por devolver",
    field: saldoReserva,
    format: dinero,
    align: "right"
  },
  { name: "estado", label: "Estado", field: "estado", align: "left" },
  {
    name: "cobro",
    label: "Pago",
    field: item => estadoPago(item).label,
    align: "left"
  },
  { name: "acciones", label: "Detalle", field: "id", align: "right" }
];
async function cargar() {
  if (cargando.value) return;
  cargando.value = true;
  error.value = "";
  datos.value = undefined;
  const filtros = new URLSearchParams({
    desde: desde.value,
    hasta: hasta.value
  });
  if (estado.value) filtros.set("estado", estado.value);
  try {
    datos.value = await api<Reporte>("/reportes/reservas?" + filtros);
  } catch (problema) {
    error.value = (problema as Error).message;
  } finally {
    cargando.value = false;
  }
}
function celdaCsv(valor: unknown) {
  let texto = String(valor ?? "");
  if (/^\s*[=+\-@]/.test(texto) || /^[\t\r\n]/.test(texto)) texto = "'" + texto;
  return '"' + texto.replaceAll('"', '""') + '"';
}
function descargar() {
  if (!datos.value) return;
  const encabezados = [
    "Reserva",
    "Cliente",
    "Inicio",
    "Fin",
    "Total S/",
    "Abonado neto S/",
    "Saldo o devolución S/",
    "Estado de reserva",
    "Estado de pago",
    "Último método"
  ];
  const filas = datos.value.reservas.map(item => [
    item.id,
    item.cliente,
    horario(item.inicio),
    horario(item.fin),
    item.total,
    item.abonado,
    saldoReserva(item).toFixed(2),
    estados[item.estado],
    estadoPago(item).label,
    item.metodo_pago ? nombreMetodo(item.metodo_pago) : "Sin pago"
  ]);
  const csv =
    "\uFEFF" +
    [encabezados, ...filas]
      .map(fila => fila.map(celdaCsv).join(";"))
      .join("\r\n");
  const resultado = exportFile(
    "reservas-" + datos.value.desde + "-a-" + datos.value.hasta + ".csv",
    csv,
    "text/csv;charset=utf-8;"
  );
  if (resultado !== true)
    q.notify({
      type: "negative",
      message: "El navegador no permitió descargar el archivo."
    });
}
onMounted(cargar);
</script>

<style scoped>
.importe {
  font-size: clamp(20px, 2vw, 30px);
  overflow-wrap: anywhere;
}
</style>
