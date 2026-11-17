<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div><span class="eyebrow">GESTIÓN DE ALQUILERES</span><h1>Reservas</h1><p>Consulta la agenda y el historial de la cancha.</p></div>
      <q-btn color="primary" unelevated no-caps icon="add" label="Nueva reserva" @click="editar()" />
    </div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
    <section class="panel">
      <div class="table-toolbar">
        <q-input v-model="busqueda" class="table-search" outlined dense clearable placeholder="Buscar por cliente…" aria-label="Buscar por cliente">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <div class="row items-center q-gutter-sm">
          <q-input v-model="fecha" type="date" stack-label outlined dense clearable label="Fecha" />
          <q-select v-model="estado" :options="opcionesEstado" emit-value map-options clearable outlined dense label="Estado" style="min-width: 145px" />
          <q-btn flat round dense icon="refresh" aria-label="Actualizar reservas" @click="cargar" />
        </div>
      </div>
      <q-table flat class="data-table" :rows="reservas" :columns="columnas" :filter="busqueda || ''" :loading="cargando"
        row-key="id" :pagination="{ rowsPerPage: 10 }" no-data-label="Aún no hay reservas para mostrar" no-results-label="No se encontraron coincidencias">
        <template #body-cell-estado="props"><q-td :props="props">
          <q-badge outline :color="colores[props.row.estado as Estado]" :label="estados[props.row.estado as Estado]" />
        </q-td></template>
        <template #body-cell-pagado="props"><q-td :props="props" class="text-right">{{ dinero(props.row.pagado) }}</q-td></template>
        <template #body-cell-saldo="props"><q-td :props="props" class="text-right">{{ dinero(props.row.saldo) }}</q-td></template>
        <template #body-cell-acciones="props"><q-td :props="props">
          <div v-if="props.row.estado === 'confirmada'" class="row no-wrap justify-end">
            <q-btn flat dense round color="green-8" icon="chat" aria-label="Confirmar por WhatsApp"
              title="Preparar mensaje de WhatsApp" :href="enlaceWhatsApp(props.row)" target="_blank" rel="noopener"
              :disable="!telefonoContacto(props.row)" />
            <q-btn flat dense no-caps color="primary" icon="edit" label="Editar" @click="editar(props.row)" />
            <q-btn v-if="props.row.fin <= horaLocal()" flat dense no-caps color="green-9" label="Completar" @click="cambiarEstado(props.row, 'completada')" />
            <q-btn flat dense no-caps color="red-8" label="Cancelar" @click="cambiarEstado(props.row, 'cancelada')" />
          </div>
          <div v-else-if="props.row.estado === 'cancelada'" class="row no-wrap justify-end">
            <q-btn flat dense no-caps color="primary" icon="event_repeat" label="Reservar de nuevo" @click="reservarDeNuevo(props.row)" />
          </div>
          <span v-else>—</span>
        </q-td></template>
      </q-table>
    </section>
    <q-dialog v-model="abierto" :persistent="guardando">
      <q-card class="dialog-card">
        <q-card-section><h2>{{ datos.id ? 'Editar reserva' : 'Nueva reserva' }}</h2><div class="text-caption text-grey-7">{{ cancha?.nombre }}</div></q-card-section>
        <q-separator />
        <q-card-section v-if="!datos.id && (!cancha?.activa || !cancha.tarifa)">
          <p>Para programar una reserva, verifica la disponibilidad y la tarifa de la cancha.</p>
          <q-btn flat color="primary" no-caps label="Configurar cancha" to="/cancha" />
          <q-btn flat no-caps label="Cerrar" v-close-popup />
        </q-card-section>
        <q-form v-else @submit="guardar">
          <q-card-section class="form-grid">
            <q-banner v-if="errorFormulario" rounded class="full-field bg-red-1 text-red-9" role="alert">{{ errorFormulario }}</q-banner>
            <q-select v-if="!registroNuevo" v-model="datos.cliente_id" :options="clientes" option-value="id"
              :option-label="etiquetaCliente" emit-value map-options clearable outlined class="full-field"
              label="Cliente registrado (opcional)">
              <template #no-option><q-item><q-item-section class="text-grey-7">Aún no hay clientes registrados.</q-item-section></q-item></template>
            </q-select>
            <div class="full-field">
              <q-btn v-if="!registroNuevo" flat no-caps color="primary" icon="person_add" label="Registrar cliente nuevo" @click="registroNuevo = true" />
              <div v-else class="row items-center justify-between q-gutter-sm">
                <div>
                  <div class="text-weight-medium">Datos del cliente nuevo</div>
                  <div class="text-caption text-grey-7">Todos los campos son opcionales.</div>
                </div>
                <q-btn flat no-caps color="primary" label="Elegir cliente registrado" @click="registroNuevo = false" />
              </div>
            </div>
            <template v-if="registroNuevo">
              <q-input v-model="nuevoCliente.dni" outlined label="DNI" inputmode="numeric" maxlength="20" />
              <q-input v-model="nuevoCliente.telefono" outlined label="Teléfono" type="tel" maxlength="30" />
              <q-input v-model="nuevoCliente.nombre" outlined class="full-field" label="Nombre y apellido" maxlength="120" />
            </template>
            <div class="full-field">
              <div class="text-caption text-grey-7 q-mb-xs">Fecha rápida</div>
              <div class="row q-gutter-sm">
                <q-btn :outline="!esFechaRapida(0)" color="primary" no-caps icon="today" label="Hoy" @click="aplicarFechaRapida(0)" />
                <q-btn :outline="!esFechaRapida(1)" color="primary" no-caps icon="event" label="Mañana" @click="aplicarFechaRapida(1)" />
                <q-input v-model="fechaElegida" type="date" outlined dense label="Elegir fecha" :min="fechaBogota(0)"
                  style="min-width: 170px" @update:model-value="aplicarOtraFecha" />
              </div>
              <div v-if="datos.inicio && datos.fin" class="text-caption text-grey-7 q-mt-sm">
                Horario seleccionado: {{ horario(datos.inicio) }} – {{ datos.fin.slice(11, 16) }}
              </div>
              <div class="row q-gutter-sm q-mt-sm">
                <q-input class="col" :model-value="datos.inicio.slice(11, 16)" type="time" outlined dense label="Hora de inicio"
                  :disable="!datos.inicio" @update:model-value="actualizarHora('inicio', $event)" />
                <q-input class="col" :model-value="datos.fin.slice(11, 16)" type="time" outlined dense label="Hora de fin"
                  :disable="!datos.fin" @update:model-value="actualizarHora('fin', $event)" />
              </div>
            </div>
            <div v-if="!datos.id" class="full-field">
              <q-toggle v-model="repetirSemanal" label="Repetir reserva cada semana" @update:model-value="cargarVistaRepeticiones" />
              <div v-if="repetirSemanal" class="q-mt-sm">
                <q-input v-model.number="cantidadRepeticiones" type="number" outlined dense label="Cantidad de fechas (incluye esta)"
                  min="2" max="52" style="max-width: 240px" @update:model-value="normalizarCantidad" />
                <div v-if="vistaRepeticiones.length" class="text-caption q-mt-sm">
                  <div v-for="ocurrencia in vistaRepeticiones" :key="ocurrencia.inicio" class="row items-center q-gutter-sm q-py-xs">
                    <span>{{ horario(ocurrencia.inicio) }}</span>
                    <q-badge :color="ocurrencia.conflicto ? 'red-8' : 'green-8'"
                      :label="ocurrencia.conflicto ? 'Cruce con otra reserva' : 'Disponible'" />
                  </div>
                </div>
              </div>
            </div>
            <div class="full-field row justify-between items-center q-gutter-sm">
              <span class="text-caption text-grey-7">Hora de Bogotá · {{ dinero(tarifa) }} por hora</span>
              <strong v-if="importe !== null">Total estimado {{ dinero(importe) }}</strong>
            </div>
            <div class="full-field q-pa-md rounded-borders bg-grey-1">
              <div class="text-weight-medium">{{ datos.id ? 'Pago de esta reserva' : 'Pago inicial' }}</div>
              <div class="text-caption text-grey-7 q-mb-sm">
                <template v-if="datos.id">Pagado {{ dinero(datos.pagado ?? 0) }} · Saldo {{ dinero(datos.saldo ?? 0) }}</template>
                <template v-else>
                  Indica si deja un adelanto o paga el total.
                  <span v-if="repetirSemanal"> El pago seleccionado se aplicará a cada fecha repetida.</span>
                </template>
              </div>
              <div class="row q-gutter-sm items-start">
                <q-select v-model="estadoPagoFormulario" :options="opcionesPagoFormulario" emit-value map-options outlined dense
                  :label="datos.id ? 'Registrar pago' : 'Estado del pago'" style="min-width: 200px" />
                <q-input v-if="estadoPagoFormulario === 'adelanto'" v-model.number="montoPago" outlined dense type="number"
                  min="0.01" step="0.01" :max="limitePago" :label="repetirSemanal ? 'Adelanto por fecha (S/)' : 'Monto entregado (S/)'" style="min-width: 180px" />
                <q-select v-if="estadoPagoFormulario !== 'pendiente'" v-model="metodoPago" :options="opcionesMetodoPago"
                  emit-value map-options outlined dense label="Método" style="min-width: 180px" />
              </div>
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cerrar" v-close-popup :disable="guardando" />
            <q-btn color="primary" unelevated no-caps
              :label="repetirSemanal && !datos.id ? `Crear ${cantidadRepeticiones} reservas` : 'Guardar reserva'"
              type="submit" :loading="guardando" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQuasar, type QTableColumn } from 'quasar';
import { api, colores, dinero, estados, horario, type Cancha, type Cliente, type EntradaEspera, type Estado, type Reserva } from '@/services/api';

type Formulario = { id: number; cliente_id: number | null; inicio: string; fin: string; tarifa?: number; pagado?: number | string; saldo?: number | string };
const q = useQuasar();
const route = useRoute();
const router = useRouter();
const cancha = ref<Cancha>();
const clientes = ref<Cliente[]>([]);
const reservas = ref<Reserva[]>([]);
const reservasParaCruces = ref<Reserva[]>([]);
const fecha = ref('');
const estado = ref<Estado | null>(null);
const busqueda = ref('');
const error = ref('');
const errorFormulario = ref('');
const cargando = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const fechaElegida = ref('');
const datos = ref<Formulario>({ id: 0, cliente_id: null, inicio: '', fin: '' });
const estadoPagoFormulario = ref<'pendiente' | 'completo' | 'adelanto'>('pendiente');
const montoPago = ref<number | null>(null);
const metodoPago = ref('efectivo');
const repetirSemanal = ref(false);
const cantidadRepeticiones = ref(4);
const registroNuevo = ref(false);
const esperaId = ref<number | null>(null);
const nuevoCliente = ref({ dni: '', nombre: '', telefono: '' });
const tarifa = computed(() => datos.value.tarifa ?? cancha.value?.tarifa ?? 0);
const importe = computed(() => {
  const minutos = (Date.parse(datos.value.fin + 'Z') - Date.parse(datos.value.inicio + 'Z')) / 60000;
  return minutos > 0 ? tarifa.value * minutos / 60 : null;
});
const limitePago = computed(() => datos.value.id ? Number(datos.value.saldo ?? 0) : (importe.value ?? 0));
const opcionesPagoFormulario = computed(() => datos.value.id
  ? [{ value: 'pendiente', label: 'Sin pago ahora' }, { value: 'completo', label: 'Cancelar saldo completo' }, { value: 'adelanto', label: 'Abonar una cantidad' }]
  : [{ value: 'pendiente', label: 'Pendiente de pago' }, { value: 'completo', label: 'Pagado completo' }, { value: 'adelanto', label: 'Dejó un adelanto' }]);
const opcionesMetodoPago = [
  { value: 'efectivo', label: 'Efectivo' }, { value: 'transferencia', label: 'Transferencia' },
  { value: 'tarjeta', label: 'Tarjeta' }, { value: 'yape_plin', label: 'Yape / Plin' }, { value: 'otro', label: 'Otro' },
];
const vistaRepeticiones = computed(() => {
  if (!repetirSemanal.value || !datos.value.inicio || !datos.value.fin) return [];
  const filas: { inicio: string; fin: string; conflicto: boolean }[] = [];
  for (let i = 0; i < cantidadRepeticiones.value; i++) {
    const inicio = sumarSemanas(datos.value.inicio, i);
    const fin = sumarSemanas(datos.value.fin, i);
    const conflicto = reservasParaCruces.value.some(reserva => reserva.estado !== 'cancelada'
      && reserva.inicio < fin && reserva.fin > inicio);
    filas.push({ inicio, fin, conflicto });
  }
  return filas;
});
const opcionesEstado = Object.entries(estados).map(([value, label]) => ({ value, label }));
const columnas: QTableColumn<Reserva>[] = [
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
  { name: 'inicio', label: 'Inicio', field: 'inicio', align: 'left', format: horario, sortable: true },
  { name: 'fin', label: 'Fin', field: 'fin', align: 'left', format: horario },
  { name: 'total', label: 'Total (S/)', field: 'total', align: 'right', format: dinero },
  { name: 'pagado', label: 'Pagado (S/)', field: 'pagado', align: 'right', format: dinero },
  { name: 'saldo', label: 'Falta pagar (S/)', field: 'saldo', align: 'right', format: dinero },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: 'Acciones', field: 'id', align: 'right' },
];
const reloj = new Intl.DateTimeFormat('sv-SE', {
  timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
});
const horaLocal = () => reloj.format(new Date()).replace(' ', 'T');
const esFechaRapida = (dias: 0 | 1) => datos.value.inicio.slice(0, 10) === fechaBogota(dias);
const etiquetaCliente = (cliente?: Cliente) => cliente
  ? cliente.nombre || cliente.dni || cliente.telefono || 'Cliente #' + cliente.id
  : '';

function telefonoContacto(reserva: Reserva) {
  const telefono = reserva.cliente_telefono || clientes.value.find(cliente => cliente.id === reserva.cliente_id)?.telefono || '';
  return telefono.replace(/\D/g, '') ? telefono : '';
}

function enlaceWhatsApp(reserva: Reserva) {
  const digitos = telefonoContacto(reserva).replace(/\D/g, '');
  const telefono = digitos.startsWith('51') ? digitos : digitos.length <= 9 ? '51' + digitos.replace(/^0/, '') : digitos;
  const fecha = reserva.inicio.slice(0, 10).split('-').reverse().join('/');
  const fechaFin = reserva.fin.slice(0, 10) === reserva.inicio.slice(0, 10)
    ? '' : ` Fecha de fin: ${reserva.fin.slice(0, 10).split('-').reverse().join('/')}.`;
  const mensaje = `Hola ${reserva.cliente}, te confirmamos tu reserva. Fecha: ${fecha}.${fechaFin} Hora de inicio: ${reserva.inicio.slice(11, 16)}. Hora de fin: ${reserva.fin.slice(11, 16)}. Saldo pendiente: ${dinero(reserva.saldo)}. ¿Nos confirmas, por favor?`;
  return 'https://wa.me/' + telefono + '?text=' + encodeURIComponent(mensaje);
}
let secuencia = 0;

function fechaBogota(dias: number) {
  const [anio, mes, dia] = horaLocal().slice(0, 10).split('-').map(Number);
  return new Date(Date.UTC(anio, mes - 1, dia + dias)).toISOString().slice(0, 10);
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
  const [hora, minuto] = horaLocal().slice(11, 16).split(':').map(Number);
  const siguienteHora = Math.min(23 * 60 + 59, hora * 60 + minuto + 60);
  const horaInicio = datos.value.inicio.slice(11, 16)
    || String(Math.floor(siguienteHora / 60)).padStart(2, '0') + ':00';
  let horaFin = datos.value.fin.slice(11, 16);
  if (!horaFin) {
    const minutosFin = (Number(horaInicio.slice(0, 2)) * 60 + Number(horaInicio.slice(3, 5)) + 60) % (24 * 60);
    horaFin = String(Math.floor(minutosFin / 60)).padStart(2, '0') + ':' + String(minutosFin % 60).padStart(2, '0');
  }
  const [anio, mes, dia] = fecha.split('-').map(Number);
  const fechaFin = horaFin <= horaInicio ? new Date(Date.UTC(anio, mes - 1, dia + 1)).toISOString().slice(0, 10) : fecha;
  datos.value.inicio = fecha + 'T' + horaInicio;
  datos.value.fin = fechaFin + 'T' + horaFin;
}

function sumarSemanas(fechaHora: string, semanas: number) {
  const fecha = fechaHora.slice(0, 10);
  const [anio, mes, dia] = fecha.split('-').map(Number);
  const futura = new Date(Date.UTC(anio, mes - 1, dia + semanas * 7)).toISOString().slice(0, 10);
  return futura + fechaHora.slice(10);
}

function normalizarCantidad() {
  cantidadRepeticiones.value = Math.max(2, Math.min(52, Math.floor(cantidadRepeticiones.value || 2)));
}

async function cargarVistaRepeticiones() {
  if (!repetirSemanal.value) return;
  try { reservasParaCruces.value = await api<Reserva[]>('/reservas'); }
  catch (problema) { errorFormulario.value = (problema as Error).message; }
}

function actualizarHora(campo: 'inicio' | 'fin', hora: string | number | null) {
  if (!hora || typeof hora !== 'string') return;
  const actual = datos.value[campo];
  if (actual) datos.value[campo] = actual.slice(0, 11) + hora;
}

async function cargar() {
  const solicitud = ++secuencia;
  cargando.value = true;
  error.value = '';
  const filtros = new URLSearchParams();
  if (fecha.value) filtros.set('fecha', fecha.value);
  if (estado.value) filtros.set('estado', estado.value);
  try {
    const filas = await api<Reserva[]>('/reservas?' + filtros);
    if (solicitud === secuencia) reservas.value = filas;
  } catch (problema) {
    if (solicitud === secuencia) error.value = (problema as Error).message;
  } finally {
    if (solicitud === secuencia) cargando.value = false;
  }
}

async function editar(reserva?: Reserva) {
  error.value = '';
  esperaId.value = null;
  try {
    [cancha.value, clientes.value] = await Promise.all([api<Cancha>('/cancha'), api<Cliente[]>('/clientes')]);
    datos.value = reserva ? { ...reserva } : { id: 0, cliente_id: null, inicio: '', fin: '' };
    estadoPagoFormulario.value = 'pendiente';
    montoPago.value = null;
    metodoPago.value = 'efectivo';
    repetirSemanal.value = false;
    cantidadRepeticiones.value = 4;
    fechaElegida.value = datos.value.inicio.slice(0, 10);
    registroNuevo.value = false;
    nuevoCliente.value = { dni: '', nombre: '', telefono: '' };
    errorFormulario.value = '';
    abierto.value = true;
  } catch (problema) { error.value = (problema as Error).message; }
}

async function reservarDeNuevo(reserva: Reserva) {
  await editar();
  if (abierto.value) datos.value.cliente_id = reserva.cliente_id;
}

async function guardar() {
  guardando.value = true;
  errorFormulario.value = '';
  const { id, cliente_id, inicio, fin } = datos.value;
  if (!inicio || !fin) {
    errorFormulario.value = 'Selecciona una fecha para fijar el horario.';
    guardando.value = false;
    return;
  }
  if (repetirSemanal.value && vistaRepeticiones.value.some(ocurrencia => ocurrencia.conflicto)) {
    errorFormulario.value = 'Hay fechas que se cruzan con otras reservas. Cambia la fecha u hora antes de confirmar.';
    guardando.value = false;
    return;
  }
  if (estadoPagoFormulario.value === 'adelanto' && (!montoPago.value || montoPago.value <= 0 || montoPago.value > limitePago.value)) {
    errorFormulario.value = 'Ingresa un adelanto válido que no supere el saldo de la reserva.';
    guardando.value = false;
    return;
  }
  try {
    const cliente = registroNuevo.value ? { cliente_nuevo: nuevoCliente.value } : { cliente_id };
    const pago = estadoPagoFormulario.value === 'pendiente' ? undefined : {
      tipo: estadoPagoFormulario.value, monto: estadoPagoFormulario.value === 'adelanto' ? montoPago.value : undefined, metodo: metodoPago.value,
    };
    const datosReserva = { inicio, fin, ...cliente, ...(pago ? { pago } : {}) };
    const reserva = repetirSemanal.value && !id
      ? await api<Reserva[]>('/reservas/repetir', 'POST', { ...datosReserva, cantidad: cantidadRepeticiones.value })
      : await api<Reserva>(id ? '/reservas/' + id : '/reservas', id ? 'PUT' : 'POST', datosReserva);
    if (registroNuevo.value && !id) {
      const reservaInicial = Array.isArray(reserva) ? reserva[0] : reserva;
      if (reservaInicial?.cliente_id) {
        clientes.value = [...clientes.value, { id: reservaInicial.cliente_id, ...nuevoCliente.value, correo: '' }];
      }
    }
    if (esperaId.value) {
      try { await api('/lista-espera/' + esperaId.value + '/estado', 'PATCH', { estado: 'atendida' }); }
      catch { q.notify({ type: 'warning', message: 'Reserva creada; actualiza manualmente el estado de la lista de espera.' }); }
      esperaId.value = null;
    }
    abierto.value = false;
    q.notify({ type: 'positive', message: repetirSemanal.value && !id
      ? cantidadRepeticiones.value + ' reservas semanales creadas.'
      : 'Reserva guardada · ' + dinero((reserva as Reserva).total) });
    repetirSemanal.value = false;
    await cargar();
  } catch (problema) { errorFormulario.value = (problema as Error).message; }
  finally { guardando.value = false; }
}

function cambiarEstado(reserva: Reserva, destino: 'completada' | 'cancelada') {
  const cancelar = destino === 'cancelada';
  q.dialog({
    title: cancelar ? 'Cancelar reserva' : 'Completar reserva',
    message: cancelar ? 'El horario quedará libre y la reserva se conservará en el historial.' : 'La reserva quedará marcada como completada.',
    cancel: { label: 'Volver', flat: true, noCaps: true },
    ok: { label: cancelar ? 'Cancelar reserva' : 'Completar', color: cancelar ? 'negative' : 'primary', noCaps: true },
  }).onOk(async () => {
    error.value = '';
    try {
      await api('/reservas/' + reserva.id + '/estado', 'PATCH', { estado: destino });
      q.notify({ type: 'positive', message: cancelar ? 'Reserva cancelada. Horario liberado.' : 'Reserva completada.' });
      await cargar();
    } catch (problema) { error.value = (problema as Error).message; }
  });
}

watch([fecha, estado], cargar);
onMounted(async () => {
  await cargar();
  try { clientes.value = await api<Cliente[]>('/clientes'); } catch { /* El listado sigue funcionando sin la acción de WhatsApp. */ }
  if (route.query.nueva) {
    const esperaSolicitada = route.query.espera;
    await router.replace('/reservas');
    if (esperaSolicitada) {
      error.value = '';
      try {
        const idEspera = Number(esperaSolicitada);
        const [configuracion, filasClientes, solicitudes] = await Promise.all([
          api<Cancha>('/cancha'), api<Cliente[]>('/clientes'), api<EntradaEspera[]>('/lista-espera'),
        ]);
        const solicitud = solicitudes.find(fila => fila.id === idEspera);
        if (!solicitud || !['disponible', 'avisado'].includes(solicitud.estado)) throw new Error('La solicitud ya no está disponible para reservar.');
        cancha.value = configuracion;
        clientes.value = filasClientes;
        datos.value = { id: 0, cliente_id: solicitud.cliente_id, inicio: solicitud.inicio, fin: solicitud.fin };
        estadoPagoFormulario.value = 'pendiente';
        montoPago.value = null;
        metodoPago.value = 'efectivo';
        fechaElegida.value = solicitud.inicio.slice(0, 10);
        registroNuevo.value = false;
        nuevoCliente.value = { dni: '', nombre: '', telefono: '' };
        esperaId.value = solicitud.id;
        errorFormulario.value = '';
        abierto.value = true;
      } catch (problema) { error.value = (problema as Error).message; }
    } else {
      esperaId.value = null;
      await editar();
    }
  }
});
</script>
