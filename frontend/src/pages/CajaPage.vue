<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div
        ><span class="eyebrow">MOVIMIENTOS EN SOLES</span><h1>Caja diaria</h1
        ><p
          >Cobros, devoluciones y gastos registrados durante el día, en hora de
          Bogotá.</p
        ></div
      >
      <q-btn
        color="primary"
        unelevated
        no-caps
        icon="remove_circle_outline"
        label="Registrar gasto"
        @click="abrirGasto"
      />
    </div>
    <div class="row items-center q-gutter-sm q-mb-lg">
      <q-input
        v-model="fecha"
        outlined
        dense
        stack-label
        type="date"
        label="Día de caja"
      />
      <q-btn
        flat
        round
        icon="refresh"
        aria-label="Actualizar caja"
        @click="cargar"
      />
    </div>
    <q-banner
      v-if="error"
      rounded
      class="bg-red-1 text-red-9 q-mb-md"
      role="alert"
      >{{ error }}</q-banner
    >
    <q-inner-loading :showing="cargando" />
    <template v-if="caja">
      <div class="stats-grid">
        <div
          v-for="tarjeta in tarjetas"
          :key="tarjeta.nombre"
          class="stat-card"
        >
          <div class="row items-center justify-between"
            ><span>{{ tarjeta.nombre }}</span
            ><q-icon :name="tarjeta.icono" color="primary" size="22px"
          /></div>
          <strong class="importe-caja">{{ dinero(tarjeta.valor) }}</strong
          ><small>{{ tarjeta.nota }}</small>
        </div>
      </div>
      <section class="panel q-mb-lg">
        <div class="panel-heading"
          ><div
            ><h2>Resumen por método</h2
            ><p>El neto corresponde a los movimientos del día.</p></div
          ></div
        >
        <q-table
          flat
          class="data-table"
          :rows="desglose"
          :columns="columnasMetodos"
          row-key="metodo"
          hide-bottom
          :pagination="{ rowsPerPage: 0 }"
        />
      </section>
      <section class="panel">
        <div class="panel-heading"
          ><div
            ><h2>Detalle de caja</h2
            ><p
              >Los gastos anulados se conservan y se excluyen de los totales.</p
            ></div
          ></div
        >
        <q-table
          flat
          class="data-table"
          :rows="caja.movimientos"
          :columns="columnas"
          row-key="clave"
          :pagination="{ rowsPerPage: 10 }"
          no-data-label="No hay movimientos para esta fecha"
        >
          <template #body-cell-tipo="props"
            ><q-td :props="props">
              <q-badge
                outline
                :color="
                  props.row.anulado
                    ? 'grey-7'
                    : props.row.tipo === 'cobro'
                      ? 'green-9'
                      : 'orange-9'
                "
                >{{
                  props.row.anulado
                    ? "Gasto anulado"
                    : nombres[props.row.tipo as keyof typeof nombres]
                }}</q-badge
              >
            </q-td></template
          >
          <template #body-cell-acciones="props"
            ><q-td :props="props">
              <q-btn
                v-if="props.row.tipo === 'gasto' && !props.row.anulado"
                flat
                dense
                no-caps
                color="negative"
                label="Anular"
                @click="anular(props.row)"
              />
              <span v-else>—</span>
            </q-td></template
          >
        </q-table>
      </section>
    </template>
    <q-dialog v-model="abierto" :persistent="guardando">
      <q-card class="dialog-card">
        <q-card-section
          ><h2>Registrar gasto</h2
          ><p class="text-caption text-grey-7 q-mb-none"
            >Se registrará en la fecha actual de Bogotá.</p
          ></q-card-section
        >
        <q-form @submit="guardar">
          <q-card-section class="form-grid">
            <q-banner
              v-if="errorGasto"
              class="full-field bg-red-1 text-red-9"
              rounded
              role="alert"
              >{{ errorGasto }}</q-banner
            >
            <q-input
              v-model="form.concepto"
              class="full-field"
              outlined
              label="Concepto del gasto"
              maxlength="150"
              :rules="[v => !!v?.trim() || 'Indica el concepto']"
            />
            <q-input
              v-model="form.monto"
              outlined
              type="number"
              min="0.01"
              max="99999999.99"
              step="0.01"
              prefix="S/"
              label="Importe"
              :rules="[validarMonto]"
            />
            <q-select
              v-model="form.metodo"
              outlined
              :options="metodos"
              emit-value
              map-options
              label="Método de pago"
            />
          </q-card-section>
          <q-card-actions align="right"
            ><q-btn
              flat
              no-caps
              label="Cerrar"
              v-close-popup
              :disable="guardando" /><q-btn
              color="primary"
              unelevated
              no-caps
              type="submit"
              label="Guardar gasto"
              :loading="guardando"
          /></q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { uid, useQuasar, type QTableColumn } from "quasar";
import { api, dinero, horario } from "@/services/api";
import {
  fechaLocal,
  metodos,
  nombreMetodo,
  validarMonto,
  type Caja,
  type Metodo,
  type Movimiento,
  type Totales
} from "@/services/finanzas";

const q = useQuasar();
const fecha = ref(fechaLocal());
const caja = ref<Caja>();
const error = ref("");
const errorGasto = ref("");
const cargando = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const form = ref({
  operacion: uid(),
  concepto: "",
  monto: "",
  metodo: "efectivo" as Metodo
});
const nombres = { cobro: "Cobro", devolucion: "Devolución", gasto: "Gasto" };
const tarjetas = computed(() =>
  caja.value
    ? [
        {
          nombre: "Cobros",
          valor: caja.value.totales.ingresos,
          nota: "Dinero recibido",
          icono: "south_west"
        },
        {
          nombre: "Devoluciones",
          valor: caja.value.totales.devoluciones,
          nota: "Dinero devuelto",
          icono: "north_east"
        },
        {
          nombre: "Gastos",
          valor: caja.value.totales.gastos,
          nota: "Gastos vigentes",
          icono: "receipt_long"
        },
        {
          nombre: "Neto del día",
          valor: caja.value.totales.neto,
          nota: "Cobros menos devoluciones y gastos",
          icono: "account_balance_wallet"
        }
      ]
    : []
);
const desglose = computed(() =>
  caja.value
    ? metodos.map(item => ({
        metodo: item.label,
        ...caja.value!.porMetodo[item.value as Metodo]
      }))
    : []
);
const columnasMetodos: QTableColumn<Totales & { metodo: string }>[] = [
  { name: "metodo", label: "Método", field: "metodo", align: "left" },
  {
    name: "ingresos",
    label: "Cobros",
    field: "ingresos",
    format: dinero,
    align: "right"
  },
  {
    name: "devoluciones",
    label: "Devoluciones",
    field: "devoluciones",
    format: dinero,
    align: "right"
  },
  {
    name: "gastos",
    label: "Gastos",
    field: "gastos",
    format: dinero,
    align: "right"
  },
  { name: "neto", label: "Neto", field: "neto", format: dinero, align: "right" }
];
const columnas: QTableColumn<Movimiento>[] = [
  {
    name: "fecha",
    label: "Fecha y hora",
    field: "fecha",
    format: valor => horario(valor.replace(" ", "T")),
    align: "left"
  },
  { name: "tipo", label: "Movimiento", field: "tipo", align: "left" },
  { name: "concepto", label: "Concepto", field: "concepto", align: "left" },
  {
    name: "metodo",
    label: "Método",
    field: "metodo",
    format: nombreMetodo,
    align: "left"
  },
  {
    name: "monto",
    label: "Importe",
    field: "monto",
    format: dinero,
    align: "right"
  },
  { name: "acciones", label: "Acciones", field: "id", align: "right" }
];
let secuencia = 0;
async function cargar() {
  const actual = ++secuencia;
  if (!fecha.value) {
    caja.value = undefined;
    cargando.value = false;
    return;
  }
  cargando.value = true;
  error.value = "";
  try {
    const resultado = await api<Caja>("/caja?fecha=" + fecha.value);
    if (actual === secuencia) caja.value = resultado;
  } catch (problema) {
    if (actual === secuencia) error.value = (problema as Error).message;
  } finally {
    if (actual === secuencia) cargando.value = false;
  }
}
function abrirGasto() {
  form.value = {
    operacion: uid(),
    concepto: "",
    monto: "",
    metodo: "efectivo"
  };
  errorGasto.value = "";
  abierto.value = true;
}
async function guardar() {
  guardando.value = true;
  errorGasto.value = "";
  try {
    await api("/gastos", "POST", form.value);
    abierto.value = false;
    q.notify({
      type: "positive",
      message: "Gasto registrado en la caja de hoy."
    });
    if (fecha.value !== fechaLocal()) fecha.value = fechaLocal();
    else await cargar();
  } catch (problema) {
    errorGasto.value = (problema as Error).message;
  } finally {
    guardando.value = false;
  }
}
function anular(gasto: Movimiento) {
  q.dialog({
    title: "Anular gasto",
    message: "Se conservará el registro y se excluirá del total de gastos.",
    cancel: { label: "Volver", flat: true, noCaps: true },
    ok: { label: "Anular gasto", color: "negative", noCaps: true }
  }).onOk(async () => {
    try {
      await api("/gastos/" + gasto.id + "/anular", "PATCH");
      q.notify({ type: "positive", message: "Gasto anulado." });
      await cargar();
    } catch (problema) {
      error.value = (problema as Error).message;
    }
  });
}
watch(fecha, cargar);
onMounted(cargar);
</script>

<style scoped>
.importe-caja {
  font-size: clamp(20px, 2.2vw, 30px);
  overflow-wrap: anywhere;
}
</style>
