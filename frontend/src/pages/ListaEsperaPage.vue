<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div><span class="eyebrow">GESTIÓN DE ALQUILERES</span><h1>Lista de espera</h1><p>Registra horarios solicitados y revisa cuando se libera uno.</p></div>
      <q-btn color="primary" unelevated no-caps icon="add" label="Agregar solicitud" @click="abrirFormulario" />
    </div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
    <section class="panel">
      <div class="table-toolbar">
        <q-input v-model="busqueda" class="table-search" outlined dense clearable placeholder="Buscar por cliente…" aria-label="Buscar por cliente">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <q-btn flat round dense icon="refresh" aria-label="Actualizar lista de espera" @click="cargar" />
      </div>
      <q-banner v-if="hayDisponibles" rounded class="bg-green-1 text-green-10 q-mx-md q-mb-md">
        Hay horarios liberados para contactar. Revisa las solicitudes marcadas como disponibles.
      </q-banner>
      <q-table flat class="data-table" :rows="solicitudes" :columns="columnas" :filter="busqueda || ''" :loading="cargando"
        row-key="id" :pagination="{ rowsPerPage: 10 }" no-data-label="Aún no hay solicitudes en espera" no-results-label="No se encontraron coincidencias">
        <template #body-cell-inicio="props"><q-td :props="props">{{ horario(props.row.inicio) }} – {{ props.row.fin.slice(11, 16) }}</q-td></template>
        <template #body-cell-estado="props"><q-td :props="props">
          <q-badge outline :color="coloresEspera[props.row.estado as EntradaEspera['estado']]" :label="estadosEspera[props.row.estado as EntradaEspera['estado']]" />
        </q-td></template>
        <template #body-cell-acciones="props"><q-td :props="props">
          <div v-if="['pendiente', 'disponible', 'avisado'].includes(props.row.estado)" class="row no-wrap justify-end">
            <q-btn v-if="props.row.estado === 'disponible'" flat dense no-caps color="primary" icon="call" label="Marcar avisado" @click="cambiarEstado(props.row, 'avisado')" />
            <q-btn v-if="props.row.estado === 'disponible' || props.row.estado === 'avisado'" flat dense no-caps color="green-9" icon="event_available" label="Crear reserva" :to="{ path: '/reservas', query: { nueva: '1', espera: String(props.row.id) } }" />
            <q-btn flat dense no-caps color="red-8" label="Quitar" @click="cambiarEstado(props.row, 'cancelada')" />
          </div>
          <span v-else>—</span>
        </q-td></template>
      </q-table>
    </section>

    <q-dialog v-model="abierto" :persistent="guardando">
      <q-card class="dialog-card">
        <q-card-section><h2>Agregar a la lista de espera</h2><p class="text-grey-7 q-mb-none">Si el horario se libera por una cancelación, la solicitud aparecerá como disponible.</p></q-card-section>
        <q-separator />
        <q-form @submit="guardar">
          <q-card-section class="form-grid">
            <q-banner v-if="errorFormulario" rounded class="full-field bg-red-1 text-red-9" role="alert">{{ errorFormulario }}</q-banner>
            <q-select v-if="!clienteNuevoActivo" v-model="datos.cliente_id" :options="clientes" option-value="id" :option-label="etiquetaCliente"
              emit-value map-options clearable outlined class="full-field" label="Cliente registrado" :rules="[v => v !== null || 'Selecciona un cliente']">
              <template #no-option><q-item><q-item-section class="text-grey-7">Aún no hay clientes registrados.</q-item-section></q-item></template>
            </q-select>
            <div class="full-field">
              <q-btn v-if="!clienteNuevoActivo" flat no-caps color="primary" icon="person_add" label="Registrar cliente nuevo" @click="clienteNuevoActivo = true; datos.cliente_id = null" />
              <div v-else class="row items-center justify-between q-gutter-sm">
                <div><div class="text-weight-medium">Datos del cliente nuevo</div><div class="text-caption text-grey-7">El nombre es obligatorio.</div></div>
                <q-btn flat no-caps color="primary" label="Elegir cliente registrado" @click="clienteNuevoActivo = false" />
              </div>
            </div>
            <template v-if="clienteNuevoActivo">
              <q-input v-model="nuevoCliente.nombre" outlined class="full-field" label="Nombre y apellido" maxlength="120" :rules="[v => !!v?.trim() || 'Ingresa el nombre']" />
              <q-input v-model="nuevoCliente.telefono" outlined label="Teléfono" type="tel" maxlength="30" />
              <q-input v-model="nuevoCliente.dni" outlined label="DNI" maxlength="20" />
            </template>
            <q-input v-model="datos.inicio" outlined stack-label type="datetime-local" label="Horario solicitado · inicio" :rules="[v => !!v || 'Indica el inicio']" />
            <q-input v-model="datos.fin" outlined stack-label type="datetime-local" label="Horario solicitado · fin" :rules="[v => !!v && v > datos.inicio || 'El fin debe ser posterior al inicio']" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cerrar" v-close-popup :disable="guardando" />
            <q-btn color="primary" unelevated no-caps label="Guardar solicitud" type="submit" :loading="guardando" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useQuasar, type QTableColumn } from 'quasar';
import { api, horario, type Cliente, type EntradaEspera } from '@/services/api';

const q = useQuasar();
const solicitudes = ref<EntradaEspera[]>([]);
const clientes = ref<Cliente[]>([]);
const busqueda = ref('');
const error = ref('');
const errorFormulario = ref('');
const cargando = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const clienteNuevoActivo = ref(false);
const datos = ref({ cliente_id: null as number | null, inicio: '', fin: '' });
const nuevoCliente = ref({ dni: '', nombre: '', telefono: '' });
const estadosEspera: Record<EntradaEspera['estado'], string> = {
  pendiente: 'En espera', disponible: 'Horario liberado', avisado: 'Cliente avisado', atendida: 'Reservada', cancelada: 'Cancelada',
};
const coloresEspera: Record<EntradaEspera['estado'], string> = {
  pendiente: 'blue-grey-7', disponible: 'green-9', avisado: 'cyan-9', atendida: 'grey-7', cancelada: 'red-8',
};
const hayDisponibles = computed(() => solicitudes.value.some(s => s.estado === 'disponible'));
const columnas: QTableColumn<EntradaEspera>[] = [
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
  { name: 'inicio', label: 'Horario solicitado', field: 'inicio', align: 'left', sortable: true },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left', sortable: true },
  { name: 'acciones', label: 'Acciones', field: 'id', align: 'right' },
];
const etiquetaCliente = (cliente?: Cliente) => cliente
  ? cliente.nombre || cliente.dni || cliente.telefono || 'Cliente #' + cliente.id
  : '';

async function cargar() {
  cargando.value = true;
  error.value = '';
  try {
    [solicitudes.value, clientes.value] = await Promise.all([api<EntradaEspera[]>('/lista-espera'), api<Cliente[]>('/clientes')]);
  } catch (problema) { error.value = (problema as Error).message; }
  finally { cargando.value = false; }
}
function abrirFormulario() {
  datos.value = { cliente_id: null, inicio: '', fin: '' };
  nuevoCliente.value = { dni: '', nombre: '', telefono: '' };
  clienteNuevoActivo.value = false;
  errorFormulario.value = '';
  abierto.value = true;
}
async function guardar() {
  guardando.value = true;
  errorFormulario.value = '';
  try {
    const cliente = clienteNuevoActivo.value ? { cliente_nuevo: nuevoCliente.value } : { cliente_id: datos.value.cliente_id };
    await api('/lista-espera', 'POST', { inicio: datos.value.inicio, fin: datos.value.fin, ...cliente });
    abierto.value = false;
    q.notify({ type: 'positive', message: 'Solicitud agregada a la lista de espera.' });
    await cargar();
  } catch (problema) { errorFormulario.value = (problema as Error).message; }
  finally { guardando.value = false; }
}
function cambiarEstado(solicitud: EntradaEspera, estado: 'avisado' | 'cancelada') {
  const cancelar = estado === 'cancelada';
  q.dialog({
    title: cancelar ? 'Quitar de la lista' : 'Marcar como avisado',
    message: cancelar ? 'La solicitud quedará en el historial como cancelada.' : 'Confirma que ya contactaste al cliente para ofrecerle el horario.',
    cancel: { label: 'Volver', flat: true, noCaps: true },
    ok: { label: cancelar ? 'Quitar solicitud' : 'Marcar avisado', color: cancelar ? 'negative' : 'primary', noCaps: true },
  }).onOk(async () => {
    try {
      await api('/lista-espera/' + solicitud.id + '/estado', 'PATCH', { estado });
      q.notify({ type: 'positive', message: cancelar ? 'Solicitud quitada.' : 'Solicitud marcada como avisada.' });
      await cargar();
    } catch (problema) { error.value = (problema as Error).message; }
  });
}
onMounted(cargar);
</script>
