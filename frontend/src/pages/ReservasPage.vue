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
        <template #body-cell-acciones="props"><q-td :props="props">
          <div v-if="props.row.estado === 'confirmada'" class="row no-wrap justify-end">
            <q-btn flat dense no-caps color="primary" icon="edit" label="Editar" @click="editar(props.row)" />
            <q-btn v-if="props.row.fin <= horaLocal()" flat dense no-caps color="green-9" label="Completar" @click="cambiarEstado(props.row, 'completada')" />
            <q-btn flat dense no-caps color="red-8" label="Cancelar" @click="cambiarEstado(props.row, 'cancelada')" />
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
            <q-input v-model="datos.inicio" outlined stack-label type="datetime-local" label="Inicio" :rules="[v => !!v || 'Indica el inicio']" />
            <q-input v-model="datos.fin" outlined stack-label type="datetime-local" label="Fin"
              :rules="[v => !!v && v > datos.inicio || 'El fin debe ser posterior al inicio']" />
            <div class="full-field row justify-between items-center q-gutter-sm">
              <span class="text-caption text-grey-7">Hora de Bogotá · {{ dinero(tarifa) }} por hora</span>
              <strong v-if="importe !== null">Total estimado {{ dinero(importe) }}</strong>
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cerrar" v-close-popup :disable="guardando" />
            <q-btn color="primary" unelevated no-caps label="Guardar reserva" type="submit" :loading="guardando" />
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
import { api, colores, dinero, estados, horario, type Cancha, type Cliente, type Estado, type Reserva } from '@/services/api';

type Formulario = { id: number; cliente_id: number | null; inicio: string; fin: string; tarifa?: number };
const q = useQuasar();
const route = useRoute();
const router = useRouter();
const cancha = ref<Cancha>();
const clientes = ref<Cliente[]>([]);
const reservas = ref<Reserva[]>([]);
const fecha = ref('');
const estado = ref<Estado | null>(null);
const busqueda = ref('');
const error = ref('');
const errorFormulario = ref('');
const cargando = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const datos = ref<Formulario>({ id: 0, cliente_id: null, inicio: '', fin: '' });
const registroNuevo = ref(false);
const nuevoCliente = ref({ dni: '', nombre: '', telefono: '' });
const tarifa = computed(() => datos.value.tarifa ?? cancha.value?.tarifa ?? 0);
const importe = computed(() => {
  const minutos = (Date.parse(datos.value.fin + 'Z') - Date.parse(datos.value.inicio + 'Z')) / 60000;
  return minutos > 0 ? tarifa.value * minutos / 60 : null;
});
const opcionesEstado = Object.entries(estados).map(([value, label]) => ({ value, label }));
const columnas: QTableColumn<Reserva>[] = [
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
  { name: 'inicio', label: 'Inicio', field: 'inicio', align: 'left', format: horario, sortable: true },
  { name: 'fin', label: 'Fin', field: 'fin', align: 'left', format: horario },
  { name: 'total', label: 'Total (S/)', field: 'total', align: 'right', format: dinero },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: 'Acciones', field: 'id', align: 'right' },
];
const reloj = new Intl.DateTimeFormat('sv-SE', {
  timeZone: 'America/Bogota', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
});
const horaLocal = () => reloj.format(new Date()).replace(' ', 'T');
const etiquetaCliente = (cliente?: Cliente) => cliente
  ? cliente.nombre || cliente.dni || cliente.telefono || 'Cliente #' + cliente.id
  : '';
let secuencia = 0;

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
  try {
    [cancha.value, clientes.value] = await Promise.all([api<Cancha>('/cancha'), api<Cliente[]>('/clientes')]);
    datos.value = reserva ? { ...reserva } : { id: 0, cliente_id: null, inicio: '', fin: '' };
    registroNuevo.value = false;
    nuevoCliente.value = { dni: '', nombre: '', telefono: '' };
    errorFormulario.value = '';
    abierto.value = true;
  } catch (problema) { error.value = (problema as Error).message; }
}

async function guardar() {
  guardando.value = true;
  errorFormulario.value = '';
  const { id, cliente_id, inicio, fin } = datos.value;
  try {
    const cliente = registroNuevo.value ? { cliente_nuevo: nuevoCliente.value } : { cliente_id };
    const reserva = await api<Reserva>(id ? '/reservas/' + id : '/reservas', id ? 'PUT' : 'POST', { inicio, fin, ...cliente });
    abierto.value = false;
    q.notify({ type: 'positive', message: 'Reserva guardada · ' + dinero(reserva.total) });
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
  if (route.query.nueva) {
    await router.replace('/reservas');
    await editar();
  }
});
</script>
