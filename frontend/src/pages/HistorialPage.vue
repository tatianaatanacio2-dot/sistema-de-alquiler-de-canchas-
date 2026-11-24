<template>
  <q-page class="workspace">
    <div class="page-heading"
      ><div
        ><span class="eyebrow">SEGUIMIENTO DE OPERACIONES</span
        ><h1>Historial de cambios</h1
        ><p
          >Consulta quién realizó cada cambio y sus valores anteriores y
          nuevos.</p
        ></div
      ></div
    >
    <q-banner
      v-if="error"
      rounded
      class="bg-red-1 text-red-9 q-mb-md"
      role="alert"
      >{{ error }}</q-banner
    >
    <section class="panel">
      <div class="table-toolbar">
        <q-input
          v-model="fecha"
          outlined
          dense
          stack-label
          type="date"
          clearable
          label="Fecha"
        />
        <q-select
          v-model="entidad"
          outlined
          dense
          clearable
          :options="entidades"
          emit-value
          map-options
          label="Módulo"
          style="min-width: 190px"
        />
        <q-btn
          flat
          round
          icon="refresh"
          aria-label="Actualizar historial"
          @click="cargar"
        />
      </div>
      <q-table
        v-model:pagination="paginacion"
        flat
        class="data-table"
        row-key="id"
        :rows="cambios"
        :columns="columnas"
        :loading="cargando"
        :rows-per-page-options="[25]"
        @request="solicitar"
        no-data-label="Los cambios aparecerán aquí a partir de la activación del historial."
      >
        <template #body-cell-entidad="props"
          ><q-td :props="props"
            >{{ nombreEntidad(props.row.entidad) }} #{{
              props.row.registro_id
            }}</q-td
          ></template
        >
        <template #body-cell-acciones="props"
          ><q-td :props="props"
            ><q-btn
              flat
              dense
              no-caps
              icon="visibility"
              color="primary"
              label="Ver detalle"
              @click="detalle = props.row" /></q-td
        ></template>
      </q-table>
    </section>
    <q-dialog
      :model-value="!!detalle"
      @update:model-value="detalle = undefined"
    >
      <q-card v-if="detalle" class="dialog-card">
        <q-card-section
          ><h2>{{ detalle.accion }}</h2
          ><div class="text-caption text-grey-7"
            >{{ detalle.usuario }} ·
            {{ horario(detalle.fecha.replace(" ", "T")) }}</div
          ></q-card-section
        >
        <q-separator />
        <q-markup-table flat wrap-cells>
          <thead
            ><tr
              ><th class="text-left">Dato</th><th class="text-left">Antes</th
              ><th class="text-left">Después</th></tr
            ></thead
          >
          <tbody
            ><tr v-for="campo in campos" :key="campo.nombre"
              ><td>{{ campo.nombre }}</td
              ><td>{{ campo.antes }}</td
              ><td>{{ campo.despues }}</td></tr
            ></tbody
          >
        </q-markup-table>
        <q-card-actions align="right"
          ><q-btn flat no-caps color="primary" label="Cerrar" v-close-popup
        /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import type { QTableColumn } from "quasar";
import { api, horario } from "@/services/api";
import type { Cambio } from "@/services/finanzas";

type Pagina = { data: Cambio[]; total: number; current_page: number };
const cambios = ref<Cambio[]>([]);
const detalle = ref<Cambio>();
const fecha = ref("");
const entidad = ref<string | null>(null);
const error = ref("");
const cargando = ref(false);
const paginacion = ref({ page: 1, rowsPerPage: 25, rowsNumber: 0 });
const entidades = [
  { value: "cancha", label: "Mi cancha" },
  { value: "clientes", label: "Clientes" },
  { value: "reservas", label: "Reservas" },
  { value: "pagos", label: "Pagos" },
  { value: "gastos", label: "Caja diaria" }
];
const nombreEntidad = (valor: string) =>
  entidades.find(item => item.value === valor)?.label || valor;
const columnas: QTableColumn<Cambio>[] = [
  {
    name: "fecha",
    label: "Fecha y hora",
    field: "fecha",
    format: valor =>
      horario(valor.replace(" ", "T")) + ":" + valor.slice(17, 19),
    align: "left"
  },
  { name: "usuario", label: "Usuario", field: "usuario", align: "left" },
  { name: "entidad", label: "Registro", field: "entidad", align: "left" },
  { name: "accion", label: "Acción", field: "accion", align: "left" },
  { name: "acciones", label: "Detalle", field: "id", align: "right" }
];
const etiquetas: Record<string, string> = {
  dni: "DNI",
  nombre: "Nombre",
  telefono: "Teléfono",
  correo: "Correo",
  activa: "Disponible",
  tarifa: "Tarifa por hora",
  cliente_id: "Cliente",
  cancha_id: "Cancha",
  inicio: "Inicio",
  fin: "Fin",
  total: "Total",
  estado: "Estado",
  reserva_id: "Reserva",
  tipo: "Movimiento",
  monto: "Importe",
  metodo: "Método",
  nota: "Nota",
  concepto: "Concepto",
  anulado: "Anulado"
};
const texto = (valor: unknown) =>
  valor === null || valor === undefined || valor === "" ? "—" : String(valor);
const campos = computed(() =>
  detalle.value
    ? Object.entries(detalle.value.despues)
        .filter(
          ([clave, valor]) =>
            !["operacion", "id", "fecha"].includes(clave) &&
            detalle.value?.antes?.[clave] !== valor
        )
        .map(([clave, valor]) => ({
          nombre: etiquetas[clave] || clave,
          antes: texto(detalle.value?.antes?.[clave]),
          despues: texto(valor)
        }))
    : []
);
let secuencia = 0;
async function cargar() {
  const actual = ++secuencia;
  cargando.value = true;
  error.value = "";
  const filtros = new URLSearchParams({ page: String(paginacion.value.page) });
  if (fecha.value) filtros.set("fecha", fecha.value);
  if (entidad.value) filtros.set("entidad", entidad.value);
  try {
    const pagina = await api<Pagina>("/historial?" + filtros);
    if (actual !== secuencia) return;
    cambios.value = pagina.data;
    paginacion.value.rowsNumber = pagina.total;
    paginacion.value.page = pagina.current_page;
  } catch (problema) {
    if (actual === secuencia) error.value = (problema as Error).message;
  } finally {
    if (actual === secuencia) cargando.value = false;
  }
}
function solicitar(datos: { pagination: { page: number } }) {
  paginacion.value.page = datos.pagination.page;
  void cargar();
}
watch([fecha, entidad], () => {
  paginacion.value.page = 1;
  void cargar();
});
onMounted(cargar);
</script>
