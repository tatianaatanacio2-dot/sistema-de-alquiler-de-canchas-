<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div
        ><h1>Reservas</h1
        ><p>Consulta la agenda y el historial de la cancha.</p></div
      >
      <div class="heading-actions">
        <q-btn
          outline
          color="primary"
          no-caps
          icon="event_available"
          label="Horarios libres"
          to="/agenda"
        />
        <q-btn
          color="primary"
          unelevated
          no-caps
          icon="add"
          label="Nueva reserva"
          @click="editar()"
        />
      </div>
    </div>
    <q-banner
      v-if="error"
      rounded
      class="bg-red-1 text-red-9 q-mb-md"
      role="alert"
      >{{ error }}</q-banner
    >
    <section class="panel">
      <div class="table-toolbar reservation-toolbar">
        <q-input
          v-model="busqueda"
          class="table-search"
          outlined
          dense
          clearable
          placeholder="Buscar por cliente…"
          aria-label="Buscar por cliente"
        >
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <div class="reservation-filters">
          <q-input
            v-model="fecha"
            type="date"
            stack-label
            outlined
            dense
            clearable
            label="Fecha"
          />
          <q-select
            v-model="estado"
            :options="opcionesEstado"
            emit-value
            map-options
            clearable
            outlined
            dense
            label="Estado"
          />
          <q-btn
            flat
            round
            icon="refresh"
            aria-label="Actualizar reservas"
            @click="cargar"
          />
        </div>
      </div>
      <q-table
        flat
        class="data-table reservation-table"
        :grid="q.screen.width < 1350"
        :rows="reservas"
        :columns="columnas"
        :filter="busqueda || ''"
        :filter-method="filtrarPorCliente"
        :loading="cargando"
        row-key="id"
        :pagination="{ rowsPerPage: 10 }"
        no-data-label="Aún no hay reservas para mostrar"
        no-results-label="No se encontraron coincidencias"
      >
        <template #item="{ row }">
          <div class="col-12 col-sm-6 record-item">
            <q-card flat bordered class="record-card">
              <q-card-section>
                <div class="record-heading">
                  <h3>{{ row.cliente }}</h3>
                  <q-badge
                    outline
                    :color="colores[row.estado as Estado]"
                    :label="estados[row.estado as Estado]"
                  />
                </div>
                <dl class="record-details">
                  <div
                    ><dt>Inicio</dt><dd>{{ horario(row.inicio) }}</dd></div
                  >
                  <div
                    ><dt>Fin</dt><dd>{{ horario(row.fin) }}</dd></div
                  >
                  <div
                    ><dt>Total</dt
                    ><dd class="text-weight-bold">{{
                      dinero(row.total)
                    }}</dd></div
                  >
                  <div
                    ><dt>Abonado</dt><dd>{{ dinero(row.abonado) }}</dd></div
                  >
                  <div
                    ><dt>{{
                      row.estado === "cancelada" ? "Por devolver" : "Saldo"
                    }}</dt
                    ><dd>{{ dinero(saldoReserva(row)) }}</dd></div
                  >
                  <div
                    ><dt>Pago</dt
                    ><dd
                      ><q-badge
                        outline
                        :color="estadoPago(row).color"
                        :label="estadoPago(row).label" /></dd
                  ></div>
                </dl>
              </q-card-section>
              <q-card-actions class="record-actions">
                <q-btn
                  flat
                  no-caps
                  color="primary"
                  icon="payments"
                  label="Pagos"
                  :to="{ path: '/pagos', query: { reserva: String(row.id) } }"
                />
                <q-btn
                  flat
                  no-caps
                  color="primary"
                  icon="print"
                  label="Constancia"
                  :to="'/reservas/' + row.id + '/constancia'"
                />
                <template v-if="row.estado === 'confirmada'">
                  <q-btn
                    flat
                    no-caps
                    color="green-8"
                    icon="chat"
                    label="WhatsApp"
                    :href="enlaceWhatsApp(row)"
                    target="_blank"
                    rel="noopener"
                    :disable="!telefonoContacto(row)"
                  />
                  <q-btn
                    flat
                    no-caps
                    color="primary"
                    icon="edit"
                    label="Editar"
                    @click="editar(row)"
                  />
                  <q-btn
                    v-if="row.fin <= horaLocal()"
                    flat
                    no-caps
                    color="green-9"
                    label="Completar"
                    @click="cambiarEstado(row, 'completada')"
                  />
                  <q-btn
                    flat
                    no-caps
                    color="red-8"
                    label="Cancelar"
                    @click="cambiarEstado(row, 'cancelada')"
                  />
                </template>
                <q-btn
                  v-else-if="row.estado === 'cancelada'"
                  flat
                  no-caps
                  color="primary"
                  icon="event_repeat"
                  label="Reservar de nuevo"
                  @click="reservarDeNuevo(row)"
                />
              </q-card-actions>
            </q-card>
          </div>
        </template>
        <template #body-cell-estado="props"
          ><q-td :props="props">
            <q-badge
              outline
              :color="colores[props.row.estado as Estado]"
              :label="estados[props.row.estado as Estado]"
            /> </q-td
        ></template>
        <template #body-cell-pago="props"
          ><q-td :props="props">
            <q-badge
              outline
              :color="estadoPago(props.row).color"
              :label="estadoPago(props.row).label"
            />
            <small class="block q-mt-xs">{{
              props.row.metodo_pago
                ? nombreMetodo(props.row.metodo_pago)
                : "Sin pago registrado"
            }}</small>
          </q-td></template
        >
        <template #body-cell-acciones="props"
          ><q-td :props="props">
            <div class="reservation-table-actions">
              <q-btn
                flat
                dense
                round
                color="green-8"
                icon="chat"
                aria-label="Confirmar por WhatsApp"
                title="Preparar mensaje de WhatsApp"
                :href="enlaceWhatsApp(props.row)"
                target="_blank"
                rel="noopener"
                :disable="!telefonoContacto(props.row)"
              />
              <q-btn
                flat
                dense
                no-caps
                color="primary"
                label="Pagos"
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
                :to="'/reservas/' + props.row.id + '/constancia'"
              />
              <template v-if="props.row.estado === 'confirmada'">
                <q-btn
                  flat
                  dense
                  no-caps
                  color="primary"
                  label="Editar"
                  @click="editar(props.row)"
                />
                <q-btn
                  v-if="props.row.fin <= horaLocal()"
                  flat
                  dense
                  no-caps
                  color="green-9"
                  label="Completar"
                  @click="cambiarEstado(props.row, 'completada')"
                />
                <q-btn
                  flat
                  dense
                  no-caps
                  color="red-8"
                  label="Cancelar"
                  @click="cambiarEstado(props.row, 'cancelada')"
                />
              </template>

              <q-btn
                v-if="props.row.estado === 'cancelada'"
                flat
                dense
                no-caps
                color="primary"
                icon="event_repeat"
                label="Reservar de nuevo"
                @click="reservarDeNuevo(props.row)"
              /> </div></q-td
        ></template>
      </q-table>
    </section>
    <q-dialog
      v-model="abierto"
      :persistent="guardando"
      :maximized="q.screen.lt.sm"
    >
      <q-card class="dialog-card">
        <q-card-section
          ><h2>{{ datos.id ? "Editar reserva" : "Nueva reserva" }}</h2
          ><div class="text-caption text-grey-7">{{
            cancha?.nombre
          }}</div></q-card-section
        >
        <q-separator />
        <q-card-section v-if="!datos.id && (!cancha?.activa || !cancha.tarifa)">
          <p
            >Para programar una reserva, verifica la disponibilidad y la tarifa
            de la cancha.</p
          >
          <q-btn
            flat
            color="primary"
            no-caps
            label="Configurar cancha"
            to="/cancha"
          />
          <q-btn flat no-caps label="Cerrar" v-close-popup />
        </q-card-section>
        <q-form v-else @submit="guardar">
          <q-card-section class="form-grid">
            <q-banner
              v-if="errorFormulario"
              rounded
              class="full-field bg-red-1 text-red-9"
              role="alert"
              >{{ errorFormulario }}</q-banner
            >
            <q-select
              v-if="!registroNuevo"
              v-model="datos.cliente_id"
              :options="clientes"
              option-value="id"
              :option-label="etiquetaCliente"
              emit-value
              map-options
              clearable
              outlined
              class="full-field"
              label="Cliente registrado (opcional)"
            >
              <template #no-option
                ><q-item
                  ><q-item-section class="text-grey-7"
                    >Aún no hay clientes registrados.</q-item-section
                  ></q-item
                ></template
              >
            </q-select>
            <div class="full-field">
              <q-btn
                v-if="!registroNuevo"
                flat
                no-caps
                color="primary"
                icon="person_add"
                label="Registrar cliente nuevo"
                @click="registroNuevo = true"
              />
              <div v-else class="row items-center justify-between q-gutter-sm">
                <div>
                  <div class="text-weight-medium">Datos del cliente nuevo</div>
                  <div class="text-caption text-grey-7"
                    >Todos los campos son opcionales.</div
                  >
                </div>
                <q-btn
                  flat
                  no-caps
                  color="primary"
                  label="Elegir cliente registrado"
                  @click="registroNuevo = false"
                />
              </div>
            </div>
            <template v-if="registroNuevo">
              <q-input
                v-model="nuevoCliente.dni"
                outlined
                label="DNI"
                inputmode="numeric"
                maxlength="20"
              />
              <q-input
                v-model="nuevoCliente.telefono"
                outlined
                label="Teléfono"
                type="tel"
                maxlength="30"
              />
              <q-input
                v-model="nuevoCliente.nombre"
                outlined
                class="full-field"
                label="Nombre y apellido"
                maxlength="120"
              />
            </template>
            <div class="full-field">
              <div class="text-caption text-grey-7 q-mb-xs">Fecha rápida</div>
              <div class="row q-gutter-sm">
                <q-btn
                  :outline="!esFechaRapida(0)"
                  color="primary"
                  no-caps
                  icon="today"
                  label="Hoy"
                  @click="aplicarFechaRapida(0)"
                />
                <q-btn
                  :outline="!esFechaRapida(1)"
                  color="primary"
                  no-caps
                  icon="event"
                  label="Mañana"
                  @click="aplicarFechaRapida(1)"
                />
                <q-input
                  v-model="fechaElegida"
                  type="date"
                  outlined
                  dense
                  label="Elegir fecha"
                  :min="fechaBogota(0)"
                  style="min-width: 170px"
                  @update:model-value="aplicarOtraFecha"
                />
              </div>
              <div
                v-if="datos.inicio && datos.fin"
                class="text-caption text-grey-7 q-mt-sm"
              >
                Horario seleccionado: {{ horario(datos.inicio) }} –
                {{ datos.fin.slice(11, 16) }}
              </div>
              <div class="row q-gutter-sm q-mt-sm">
                <q-input
                  class="col"
                  :model-value="datos.inicio.slice(11, 16)"
                  type="time"
                  outlined
                  dense
                  label="Hora de inicio"
                  :disable="!datos.inicio"
                  @update:model-value="actualizarHora('inicio', $event)"
                />
                <q-input
                  class="col"
                  :model-value="datos.fin.slice(11, 16)"
                  type="time"
                  outlined
                  dense
                  label="Hora de fin"
                  :disable="!datos.fin"
                  @update:model-value="actualizarHora('fin', $event)"
                />
              </div>
            </div>
            <div v-if="!datos.id" class="full-field">
              <q-toggle
                v-model="repetirSemanal"
                label="Repetir reserva cada semana"
                @update:model-value="cargarVistaRepeticiones"
              />
              <div v-if="repetirSemanal" class="q-mt-sm">
                <q-input
                  v-model.number="cantidadRepeticiones"
                  type="number"
                  outlined
                  dense
                  label="Cantidad de fechas (incluye esta)"
                  min="2"
                  max="52"
                  style="max-width: 240px"
                  @update:model-value="normalizarCantidad"
                />
                <div
                  v-if="vistaRepeticiones.length"
                  class="text-caption q-mt-sm"
                >
                  <div
                    v-for="ocurrencia in vistaRepeticiones"
                    :key="ocurrencia.inicio"
                    class="row items-center q-gutter-sm q-py-xs"
                  >
                    <span>{{ horario(ocurrencia.inicio) }}</span>
                    <q-badge
                      :color="ocurrencia.conflicto ? 'red-8' : 'green-8'"
                      :label="
                        ocurrencia.conflicto
                          ? 'Cruce con otra reserva'
                          : 'Disponible'
                      "
                    />
                  </div>
                </div>
              </div>
            </div>
            <div
              class="full-field row justify-between items-center q-gutter-sm"
            >
              <span class="text-caption text-grey-7"
                >Hora de Bogotá · {{ dinero(tarifa) }} por hora</span
              >
              <strong v-if="importe !== null"
                >Total estimado {{ dinero(importe) }}</strong
              >
            </div>
            <div v-if="datos.inicio" class="full-field row q-gutter-xs">
              <q-btn
                v-for="minutos in [30, 60, 90]"
                :key="minutos"
                outline
                no-caps
                color="primary"
                :label="`${minutos} min`"
                @click="fijarDuracion(minutos)"
              />
            </div>
            <template v-if="!datos.id">
              <q-separator class="full-field" />
              <div class="full-field">
                <q-toggle
                  v-model="cobrarAhora"
                  color="primary"
                  label="Registrar un pago al reservar"
                />
                <p class="text-caption text-grey-7 q-mb-none">
                  Registra el dinero que ya recibiste del cliente.
                  <span v-if="repetirSemanal">
                    Este pago se aplicará a cada fecha.</span
                  >
                </p>
              </div>
              <template v-if="cobrarAhora">
                <q-select
                  v-model="modalidadPago"
                  outlined
                  label="Tipo de pago"
                  emit-value
                  map-options
                  :options="[
                    { label: 'Adelanto', value: 'adelanto' },
                    { label: 'Pago completo', value: 'completo' }
                  ]"
                />
                <q-select
                  v-model="pago.metodo"
                  outlined
                  :options="metodos"
                  emit-value
                  map-options
                  label="Método de pago"
                />
                <q-input
                  v-model="pago.monto"
                  outlined
                  type="number"
                  min="0.01"
                  step="0.01"
                  prefix="S/"
                  label="Importe recibido"
                  :readonly="modalidadPago === 'completo'"
                  :rules="[
                    validarMonto,
                    v =>
                      (importe !== null && Number(v) <= importe) ||
                      'El pago supera el total de la reserva'
                  ]"
                />
                <q-input
                  v-model="pago.nota"
                  outlined
                  maxlength="250"
                  :label="
                    pago.metodo === 'efectivo'
                      ? 'Nota (opcional)'
                      : 'Referencia o número de operación (opcional)'
                  "
                />
              </template>
              <div
                v-if="importe !== null"
                class="full-field bg-cyan-1 rounded-borders q-pa-md row justify-between items-center"
              >
                <span>{{
                  cobrarAhora ? "Saldo después del pago" : "Saldo pendiente"
                }}</span>
                <strong>{{
                  dinero(
                    Math.max(
                      0,
                      importe - (cobrarAhora ? Number(pago.monto) || 0 : 0)
                    )
                  )
                }}</strong>
              </div>
            </template>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn
              flat
              no-caps
              label="Cerrar"
              v-close-popup
              :disable="guardando"
            />
            <q-btn
              color="primary"
              unelevated
              no-caps
              :label="
                repetirSemanal && !datos.id
                  ? `Crear ${cantidadRepeticiones} reservas`
                  : !datos.id && cobrarAhora
                    ? 'Guardar reserva y pago'
                    : 'Guardar reserva'
              "
              type="submit"
              :loading="guardando"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { uid, useQuasar, type QTableColumn } from "quasar";
import {
  api,
  colores,
  dinero,
  estados,
  horario,
  type Cancha,
  type Cliente,
  type EntradaEspera,
  type Estado,
  type Reserva
} from "@/services/api";
import {
  estadoPago,
  metodos,
  nombreMetodo,
  saldoReserva,
  validarMonto,
  type Metodo
} from "@/services/finanzas";

type Formulario = {
  id: number;
  cliente_id: number | null;
  inicio: string;
  fin: string;
  tarifa?: number;
  abonado?: number | string;
  metodo_pago?: string | null;
};
const q = useQuasar();
const route = useRoute();
const router = useRouter();
const cancha = ref<Cancha>();
const clientes = ref<Cliente[]>([]);
const reservas = ref<Reserva[]>([]);
const reservasParaCruces = ref<Reserva[]>([]);
const fecha = ref("");
const estado = ref<Estado | null>(null);
const busqueda = ref("");
const error = ref("");
const errorFormulario = ref("");
const cargando = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const fechaElegida = ref("");
const datos = ref<Formulario>({ id: 0, cliente_id: null, inicio: "", fin: "" });
const repetirSemanal = ref(false);
const cantidadRepeticiones = ref(4);
const registroNuevo = ref(false);
const esperaId = ref<number | null>(null);
const nuevoCliente = ref({ dni: "", nombre: "", telefono: "" });
const cobrarAhora = ref(false);
const modalidadPago = ref<"adelanto" | "completo">("adelanto");
const pago = ref({
  operacion: uid(),
  monto: "",
  metodo: "efectivo" as Metodo,
  nota: ""
});
const tarifa = computed(() => datos.value.tarifa ?? cancha.value?.tarifa ?? 0);
const importe = computed(() => {
  const minutos =
    (Date.parse(datos.value.fin + "Z") - Date.parse(datos.value.inicio + "Z")) /
    60000;
  return minutos > 0
    ? Math.round((tarifa.value * 100 * minutos) / 60) / 100
    : null;
});
const vistaRepeticiones = computed(() => {
  if (!repetirSemanal.value || !datos.value.inicio || !datos.value.fin)
    return [];
  const filas: { inicio: string; fin: string; conflicto: boolean }[] = [];
  for (let i = 0; i < cantidadRepeticiones.value; i++) {
    const inicio = sumarSemanas(datos.value.inicio, i);
    const fin = sumarSemanas(datos.value.fin, i);
    const conflicto = reservasParaCruces.value.some(
      reserva =>
        reserva.estado !== "cancelada" &&
        reserva.inicio < fin &&
        reserva.fin > inicio
    );
    filas.push({ inicio, fin, conflicto });
  }
  return filas;
});
const opcionesEstado = Object.entries(estados).map(([value, label]) => ({
  value,
  label
}));
watch([importe, modalidadPago], () => {
  if (modalidadPago.value === "completo")
    pago.value.monto = importe.value?.toFixed(2) ?? "";
});
const columnas: QTableColumn<Reserva>[] = [
  {
    name: "cliente",
    label: "Cliente",
    field: "cliente",
    align: "left",
    sortable: true
  },
  {
    name: "inicio",
    label: "Inicio",
    field: "inicio",
    align: "left",
    format: horario,
    sortable: true
  },
  { name: "fin", label: "Fin", field: "fin", align: "left", format: horario },
  {
    name: "total",
    label: "Total (S/)",
    field: "total",
    align: "right",
    format: dinero
  },
  {
    name: "abonado",
    label: "Abonado",
    field: "abonado",
    align: "right",
    format: dinero
  },
  {
    name: "saldo",
    label: "Saldo / por devolver",
    field: saldoReserva,
    align: "right",
    format: dinero
  },
  {
    name: "pago",
    label: "Pago y método",
    field: fila => estadoPago(fila).label,
    align: "left"
  },
  { name: "estado", label: "Estado", field: "estado", align: "left" },
  { name: "acciones", label: "Acciones", field: "id", align: "right" }
];
const reloj = new Intl.DateTimeFormat("sv-SE", {
  timeZone: "America/Lima",
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
  hour: "2-digit",
  minute: "2-digit"
});
const horaLocal = () => reloj.format(new Date()).replace(" ", "T");
const esFechaRapida = (dias: 0 | 1) =>
  datos.value.inicio.slice(0, 10) === fechaBogota(dias);
const comparar = new Intl.Collator("es", {
  sensitivity: "base",
  usage: "search"
});
function filtrarPorCliente(filas: readonly Reserva[], termino: string) {
  const consulta = termino.trim().normalize("NFC");
  if (!consulta) return filas.slice();
  return filas.filter(({ cliente }) => {
    const nombre = cliente.normalize("NFC");
    for (let i = 0; i <= nombre.length - consulta.length; i++) {
      if (
        comparar.compare(nombre.slice(i, i + consulta.length), consulta) === 0
      )
        return true;
    }
    return false;
  });
}

function fijarDuracion(minutos: number) {
  const inicio = Date.parse(datos.value.inicio + "Z");
  if (Number.isFinite(inicio))
    datos.value.fin = new Date(inicio + minutos * 60000)
      .toISOString()
      .slice(0, 16);
}
const etiquetaCliente = (cliente?: Cliente) =>
  cliente
    ? cliente.nombre ||
      cliente.dni ||
      cliente.telefono ||
      "Cliente #" + cliente.id
    : "";

function telefonoContacto(reserva: Reserva) {
  const telefono =
    reserva.cliente_telefono ||
    clientes.value.find(cliente => cliente.id === reserva.cliente_id)
      ?.telefono ||
    "";
  return telefono.replace(/\D/g, "") ? telefono : "";
}

function enlaceWhatsApp(reserva: Reserva) {
  const digitos = telefonoContacto(reserva).replace(/\D/g, "");
  const telefono = digitos.startsWith("51")
    ? digitos
    : digitos.length <= 9
      ? "51" + digitos.replace(/^0/, "")
      : digitos;
  const fecha = reserva.inicio.slice(0, 10).split("-").reverse().join("/");
  const fechaFin =
    reserva.fin.slice(0, 10) === reserva.inicio.slice(0, 10)
      ? ""
      : ` Fecha de fin: ${reserva.fin.slice(0, 10).split("-").reverse().join("/")}.`;
  const mensaje = `Hola ${reserva.cliente}, te confirmamos tu reserva. Fecha: ${fecha}.${fechaFin} Hora de inicio: ${reserva.inicio.slice(11, 16)}. Hora de fin: ${reserva.fin.slice(11, 16)}. Saldo pendiente: ${dinero(saldoReserva(reserva))}. ¿Nos confirmas, por favor?`;
  return "https://wa.me/" + telefono + "?text=" + encodeURIComponent(mensaje);
}
let secuencia = 0;

function fechaBogota(dias: number) {
  const [anio, mes, dia] = horaLocal().slice(0, 10).split("-").map(Number);
  return new Date(Date.UTC(anio, mes - 1, dia + dias))
    .toISOString()
    .slice(0, 10);
}

function aplicarFechaRapida(dias: 0 | 1) {
  aplicarFecha(fechaBogota(dias));
}

function aplicarOtraFecha() {
  if (!fechaElegida.value) return;
  aplicarFecha(fechaElegida.value);
}

function aplicarFecha(fecha: string) {
  fechaElegida.value = fecha;
  const [hora, minuto] = horaLocal().slice(11, 16).split(":").map(Number);
  const siguienteHora = Math.min(23 * 60 + 59, hora * 60 + minuto + 60);
  const horaInicio =
    datos.value.inicio.slice(11, 16) ||
    String(Math.floor(siguienteHora / 60)).padStart(2, "0") + ":00";
  let horaFin = datos.value.fin.slice(11, 16);
  if (!horaFin) {
    const minutosFin =
      (Number(horaInicio.slice(0, 2)) * 60 +
        Number(horaInicio.slice(3, 5)) +
        60) %
      (24 * 60);
    horaFin =
      String(Math.floor(minutosFin / 60)).padStart(2, "0") +
      ":" +
      String(minutosFin % 60).padStart(2, "0");
  }
  const [anio, mes, dia] = fecha.split("-").map(Number);
  const fechaFin =
    horaFin <= horaInicio
      ? new Date(Date.UTC(anio, mes - 1, dia + 1)).toISOString().slice(0, 10)
      : fecha;
  datos.value.inicio = fecha + "T" + horaInicio;
  datos.value.fin = fechaFin + "T" + horaFin;
}

function sumarSemanas(fechaHora: string, semanas: number) {
  const fecha = fechaHora.slice(0, 10);
  const [anio, mes, dia] = fecha.split("-").map(Number);
  const futura = new Date(Date.UTC(anio, mes - 1, dia + semanas * 7))
    .toISOString()
    .slice(0, 10);
  return futura + fechaHora.slice(10);
}

function normalizarCantidad() {
  cantidadRepeticiones.value = Math.max(
    2,
    Math.min(52, Math.floor(cantidadRepeticiones.value || 2))
  );
}

async function cargarVistaRepeticiones() {
  if (!repetirSemanal.value) return;
  try {
    reservasParaCruces.value = await api<Reserva[]>("/reservas");
  } catch (problema) {
    errorFormulario.value = (problema as Error).message;
  }
}

function actualizarHora(campo: "inicio" | "fin", hora: string | number | null) {
  if (!hora || typeof hora !== "string") return;
  const actual = datos.value[campo];
  if (actual) datos.value[campo] = actual.slice(0, 11) + hora;
}

async function cargar() {
  const solicitud = ++secuencia;
  cargando.value = true;
  error.value = "";
  const filtros = new URLSearchParams();
  if (fecha.value) filtros.set("fecha", fecha.value);
  if (estado.value) filtros.set("estado", estado.value);
  try {
    const filas = await api<Reserva[]>("/reservas?" + filtros);
    if (solicitud === secuencia) reservas.value = filas;
  } catch (problema) {
    if (solicitud === secuencia) error.value = (problema as Error).message;
  } finally {
    if (solicitud === secuencia) cargando.value = false;
  }
}

async function editar(reserva?: Reserva) {
  error.value = "";
  esperaId.value = null;
  try {
    [cancha.value, clientes.value] = await Promise.all([
      api<Cancha>("/cancha"),
      api<Cliente[]>("/clientes")
    ]);
    datos.value = reserva
      ? { ...reserva }
      : { id: 0, cliente_id: null, inicio: "", fin: "" };
    repetirSemanal.value = false;
    cantidadRepeticiones.value = 4;
    fechaElegida.value = datos.value.inicio.slice(0, 10);
    registroNuevo.value = false;
    nuevoCliente.value = { dni: "", nombre: "", telefono: "" };
    cobrarAhora.value = false;
    modalidadPago.value = "adelanto";
    pago.value = { operacion: uid(), monto: "", metodo: "efectivo", nota: "" };
    errorFormulario.value = "";
    abierto.value = true;
  } catch (problema) {
    error.value = (problema as Error).message;
  }
}

async function reservarDeNuevo(reserva: Reserva) {
  await editar();
  if (abierto.value) datos.value.cliente_id = reserva.cliente_id;
}

async function guardar() {
  guardando.value = true;
  errorFormulario.value = "";
  const { id, cliente_id, inicio, fin } = datos.value;
  if (!inicio || !fin) {
    errorFormulario.value = "Selecciona una fecha para fijar el horario.";
    guardando.value = false;
    return;
  }
  if (
    repetirSemanal.value &&
    vistaRepeticiones.value.some(ocurrencia => ocurrencia.conflicto)
  ) {
    errorFormulario.value =
      "Hay fechas que se cruzan con otras reservas. Cambia la fecha u hora antes de confirmar.";
    guardando.value = false;
    return;
  }
  if (
    cobrarAhora.value &&
    (importe.value === null ||
      Number(pago.value.monto) <= 0 ||
      Number(pago.value.monto) > importe.value)
  ) {
    errorFormulario.value =
      "Ingresa un pago válido que no supere el total de la reserva.";
    guardando.value = false;
    return;
  }
  try {
    const cliente = registroNuevo.value
      ? { cliente_nuevo: nuevoCliente.value }
      : { cliente_id };
    const cobro = !id && cobrarAhora.value ? { pago_inicial: pago.value } : {};
    const datosReserva = { inicio, fin, ...cliente, ...cobro };
    const guardadas =
      repetirSemanal.value && !id
        ? await api<Reserva[]>("/reservas/repetir", "POST", {
            ...datosReserva,
            cantidad: cantidadRepeticiones.value
          })
        : [
            await api<Reserva>(
              id ? "/reservas/" + id : "/reservas",
              id ? "PUT" : "POST",
              datosReserva
            )
          ];
    const reserva = guardadas[0];
    if (registroNuevo.value && !id && reserva?.cliente_id) {
      clientes.value = [
        ...clientes.value,
        { id: reserva.cliente_id, ...nuevoCliente.value, correo: "" }
      ];
    }
    if (esperaId.value) {
      try {
        await api("/lista-espera/" + esperaId.value + "/estado", "PATCH", {
          estado: "atendida"
        });
      } catch {
        q.notify({
          type: "warning",
          message:
            "Reserva creada; actualiza manualmente el estado de la lista de espera."
        });
      }
      esperaId.value = null;
    }
    abierto.value = false;
    q.notify({
      type: "positive",
      message:
        repetirSemanal.value && !id
          ? cantidadRepeticiones.value + " reservas semanales creadas."
          : "Reserva guardada · " + dinero(reserva.total)
    });
    repetirSemanal.value = false;
    await cargar();
  } catch (problema) {
    errorFormulario.value = (problema as Error).message;
  } finally {
    guardando.value = false;
  }
}

function cambiarEstado(reserva: Reserva, destino: "completada" | "cancelada") {
  const cancelar = destino === "cancelada";
  q.dialog({
    title: cancelar ? "Cancelar reserva" : "Completar reserva",
    message: cancelar
      ? "El horario quedará libre. Si ya hubo pagos, registra la devolución correspondiente en Pagos."
      : "La reserva quedará marcada como completada.",
    cancel: { label: "Volver", flat: true, noCaps: true },
    ok: {
      label: cancelar ? "Cancelar reserva" : "Completar",
      color: cancelar ? "negative" : "primary",
      noCaps: true
    }
  }).onOk(async () => {
    error.value = "";
    try {
      await api("/reservas/" + reserva.id + "/estado", "PATCH", {
        estado: destino
      });
      q.notify({
        type: "positive",
        message: cancelar
          ? "Reserva cancelada. Horario liberado."
          : "Reserva completada."
      });
      await cargar();
    } catch (problema) {
      error.value = (problema as Error).message;
    }
  });
}

watch([fecha, estado], cargar);
onMounted(async () => {
  await cargar();
  try {
    clientes.value = await api<Cliente[]>("/clientes");
  } catch {
    /* El listado sigue funcionando sin la acción de WhatsApp. */
  }
  if (route.query.nueva) {
    const esperaSolicitada = route.query.espera;
    const inicio =
      typeof route.query.inicio === "string" ? route.query.inicio : "";
    const fin = typeof route.query.fin === "string" ? route.query.fin : "";
    await router.replace("/reservas");
    if (esperaSolicitada) {
      try {
        const idEspera = Number(esperaSolicitada);
        const [configuracion, filasClientes, solicitudes] = await Promise.all([
          api<Cancha>("/cancha"),
          api<Cliente[]>("/clientes"),
          api<EntradaEspera[]>("/lista-espera")
        ]);
        const solicitud = solicitudes.find(fila => fila.id === idEspera);
        if (!solicitud || !["disponible", "avisado"].includes(solicitud.estado))
          throw new Error("La solicitud ya no está disponible para reservar.");
        cancha.value = configuracion;
        clientes.value = filasClientes;
        datos.value = {
          id: 0,
          cliente_id: solicitud.cliente_id,
          inicio: solicitud.inicio,
          fin: solicitud.fin
        };
        fechaElegida.value = solicitud.inicio.slice(0, 10);
        esperaId.value = solicitud.id;
        registroNuevo.value = false;
        errorFormulario.value = "";
        abierto.value = true;
      } catch (problema) {
        error.value = (problema as Error).message;
      }
    } else {
      await editar();
      if (
        /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(inicio) &&
        /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(fin)
      ) {
        datos.value.inicio = inicio;
        datos.value.fin = fin;
        fechaElegida.value = inicio.slice(0, 10);
      }
    }
  }
});
</script>
