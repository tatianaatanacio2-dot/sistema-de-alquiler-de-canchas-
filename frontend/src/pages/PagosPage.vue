<template>
  <q-page class="workspace">
    <div class="page-heading"><div><span class="eyebrow">COBROS Y SALDOS EN SOLES</span><h1>Pagos</h1><p>Registra adelantos, completa pagos y consulta las devoluciones de cada reserva.</p></div></div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
    <section class="panel">
      <div class="table-toolbar">
        <q-input v-model="buscar" outlined dense clearable class="table-search" placeholder="Buscar por cliente o reserva…" aria-label="Buscar pagos"><template #prepend><q-icon name="search" /></template></q-input>
        <q-toggle v-model="pendientes" label="Solo saldos pendientes" />
        <q-btn flat round icon="refresh" aria-label="Actualizar pagos" @click="cargar" />
      </div>
      <q-table flat class="data-table" row-key="id" :rows="filas" :columns="columnas" :filter="buscar || ''" :loading="cargando" :pagination="{ rowsPerPage: 10 }" no-data-label="No hay reservas en esta vista" no-results-label="No se encontraron coincidencias">
        <template #body-cell-saldo="props"><q-td :props="props">
          <span :class="props.row.estado === 'cancelada' && Number(props.row.abonado) > 0 ? 'text-orange-9' : ''">{{ dinero(saldo(props.row)) }}</span>
          <small v-if="props.row.estado === 'cancelada' && Number(props.row.abonado) > 0" class="block">Por devolver</small>
        </q-td></template>
        <template #body-cell-estado="props"><q-td :props="props"><q-badge outline :color="colores[props.row.estado as Estado]" :label="estados[props.row.estado as Estado]" /></q-td></template>
        <template #body-cell-cobro="props"><q-td :props="props"><q-badge outline :color="estadoPago(props.row).color" :label="estadoPago(props.row).label" /></q-td></template>
        <template #body-cell-acciones="props"><q-td :props="props"><q-btn flat dense no-caps color="primary" icon="payments" label="Gestionar" @click="abrir(props.row)" /></q-td></template>
      </q-table>
    </section>
    <q-dialog v-model="abierto" :persistent="guardando || cargandoPagos">
      <q-card v-if="reserva" class="dialog-card">
        <q-card-section><h2>Pagos de la reserva #{{ reserva.id }}</h2><div class="text-caption text-grey-7">{{ reserva.cliente }} · {{ horario(reserva.inicio) }}</div></q-card-section>
        <q-separator />
        <q-card-section class="row q-col-gutter-md">
          <div class="col"><small>Total de reserva</small><div>{{ dinero(reserva.total) }}</div></div>
          <div class="col"><small>Abonado neto</small><div>{{ dinero(reserva.abonado) }}</div></div>
          <div class="col"><small>{{ reserva.estado === 'cancelada' ? 'Por devolver' : 'Pendiente' }}</small><div>{{ dinero(saldo(reserva)) }}</div></div>
        </q-card-section>
        <q-banner v-if="reserva.estado === 'cancelada'" class="bg-orange-1 text-orange-10 q-mx-md">La reserva está cancelada. Registra la devolución cuando entregues el dinero al cliente.</q-banner>
        <q-banner v-if="errorPago" class="bg-red-1 text-red-9 q-ma-md" rounded role="alert">{{ errorPago }}</q-banner>
        <q-inner-loading :showing="cargandoPagos" />
        <q-form @submit="guardar">
          <q-card-section class="form-grid">
            <q-select v-model="form.tipo" outlined emit-value map-options label="Movimiento" :options="tipos" />
            <q-input v-model="form.monto" outlined type="number" min="0.01" max="99999999.99" step="0.01" prefix="S/" label="Importe" :rules="[validarMonto]" />
            <q-select v-model="form.metodo" outlined :options="metodos" emit-value map-options label="Método de pago" />
            <q-input v-model="form.nota" outlined :label="form.metodo === 'efectivo' ? 'Nota (opcional)' : 'Referencia o número de operación (opcional)'" maxlength="250" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cerrar" v-close-popup :disable="guardando" />
            <q-btn color="primary" unelevated no-caps :label="form.tipo === 'cobro' ? 'Registrar cobro' : 'Registrar devolución'" type="submit" :loading="guardando" :disable="cargandoPagos" />
          </q-card-actions>
        </q-form>
        <q-separator />
        <q-card-section><h2>Movimientos registrados</h2></q-card-section>
        <q-list v-if="pagos.length" separator>
          <q-item v-for="pago in pagos" :key="pago.id">
            <q-item-section><q-item-label>{{ pago.tipo === 'cobro' ? 'Cobro' : 'Devolución' }} · {{ nombreMetodo(pago.metodo) }}</q-item-label><q-item-label caption>{{ horario(pago.fecha.replace(' ', 'T')) }}<span v-if="pago.nota"> · {{ pago.nota }}</span></q-item-label></q-item-section>
            <q-item-section side :class="pago.tipo === 'cobro' ? 'text-green-9' : 'text-orange-9'">{{ dinero(pago.monto) }}</q-item-section>
          </q-item>
        </q-list>
        <p v-else class="q-px-md text-grey-7">Esta reserva todavía no tiene pagos registrados.</p>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { uid, useQuasar, type QTableColumn } from 'quasar';
import { api, colores, dinero, estados, horario, type Estado, type Reserva } from '@/services/api';
import { estadoPago, metodos, nombreMetodo, saldoReserva as saldo, validarMonto, type Metodo, type Pago } from '@/services/finanzas';

const q = useQuasar();
const route = useRoute();
const router = useRouter();
const reservas = ref<Reserva[]>([]);
const reserva = ref<Reserva>();
const pagos = ref<Pago[]>([]);
const buscar = ref('');
const pendientes = ref(false);
const error = ref('');
const errorPago = ref('');
const cargando = ref(false);
const cargandoPagos = ref(false);
const guardando = ref(false);
const abierto = ref(false);
const form = ref({ operacion: uid(), tipo: 'cobro' as 'cobro' | 'devolucion', monto: '', metodo: 'efectivo' as Metodo, nota: '' });
const filas = computed(() => pendientes.value ? reservas.value.filter(item => saldo(item) > 0) : reservas.value);
const tipos = computed(() => reserva.value?.estado === 'cancelada'
  ? [{ label: 'Devolución', value: 'devolucion' }]
  : [{ label: 'Cobro', value: 'cobro' }, { label: 'Devolución', value: 'devolucion' }]);
const columnas: QTableColumn<Reserva>[] = [
  { name: 'id', label: 'Reserva', field: 'id', align: 'left', format: id => '#' + id },
  { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left', sortable: true },
  { name: 'inicio', label: 'Horario de inicio', field: 'inicio', format: horario, align: 'left', sortable: true },
  { name: 'total', label: 'Total', field: 'total', format: dinero, align: 'right' },
  { name: 'abonado', label: 'Abonado neto', field: 'abonado', format: dinero, align: 'right' },
  { name: 'metodo_pago', label: 'Último método de pago', field: 'metodo_pago', format: valor => valor ? nombreMetodo(valor) : 'Sin pago', align: 'left' },
  { name: 'cobro', label: 'Estado de pago', field: fila => estadoPago(fila).label, align: 'left' },
  { name: 'saldo', label: 'Saldo', field: saldo, format: dinero, align: 'right' },
  { name: 'estado', label: 'Estado', field: 'estado', align: 'left' },
  { name: 'acciones', label: 'Acciones', field: 'id', align: 'right' },
];

async function cargar() {
  cargando.value = true;
  error.value = '';
  try { reservas.value = await api<Reserva[]>('/reservas'); }
  catch (problema) { error.value = (problema as Error).message; }
  finally { cargando.value = false; }
}
async function abrir(fila: Reserva) {
  reserva.value = { ...fila };
  pagos.value = [];
  errorPago.value = '';
  form.value = { operacion: uid(), tipo: fila.estado === 'cancelada' ? 'devolucion' : 'cobro', monto: saldo(fila).toFixed(2), metodo: 'efectivo', nota: '' };
  abierto.value = true;
  cargandoPagos.value = true;
  try { pagos.value = await api<Pago[]>('/pagos?reserva_id=' + fila.id); }
  catch (problema) { errorPago.value = (problema as Error).message; }
  finally { cargandoPagos.value = false; }
}
watch(() => form.value.tipo, tipo => {
  if (reserva.value) form.value.monto = (tipo === 'devolucion' ? Number(reserva.value.abonado) : saldo(reserva.value)).toFixed(2);
});
async function guardar() {
  if (!reserva.value) return;
  guardando.value = true;
  errorPago.value = '';
  try {
    await api('/pagos', 'POST', { ...form.value, reserva_id: reserva.value.id });
    q.notify({ type: 'positive', message: 'Movimiento registrado.' });
    abierto.value = false;
    await cargar();
  } catch (problema) { errorPago.value = (problema as Error).message; }
  finally { guardando.value = false; }
}
onMounted(async () => {
  await cargar();
  if (route.query.reserva) {
    const fila = reservas.value.find(item => String(item.id) === route.query.reserva);
    await router.replace('/pagos');
    if (fila) await abrir(fila);
  }
});
</script>
