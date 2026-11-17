<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div><h1>Resumen general</h1><p>Consulta la agenda y la disponibilidad de la cancha.</p></div>
      <div class="row items-center q-gutter-sm">
        <q-btn flat round color="primary" icon="refresh" aria-label="Actualizar resumen" :loading="cargando" @click="cargar" />
        <q-btn color="primary" unelevated no-caps icon="add" label="Nueva reserva" :to="{ path: '/reservas', query: { nueva: '1' } }" />
      </div>
    </div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-lg" role="alert">{{ error }}</q-banner>
    <q-inner-loading :showing="cargando" />
    <template v-if="datos">
      <q-banner v-if="datos.demostracion" rounded class="bg-orange-1 text-orange-10 q-mb-lg">
        Este panel contiene reservas, cobros y gastos de demostración.
      </q-banner>
      <div class="stats-grid">
        <div v-for="tarjeta in tarjetas" :key="tarjeta.nombre" class="stat-card">
          <div class="row justify-between items-center">
            <div class="stat-icon" :class="tarjeta.color"><q-icon :name="tarjeta.icono" size="22px" /></div>
            <span>{{ tarjeta.nombre }}</span>
          </div>
          <strong>{{ tarjeta.valor }}</strong><small>{{ tarjeta.nota }}</small>
        </div>
      </div>
      <div class="stats-grid">
        <div v-for="tarjeta in tarjetasFinanzas" :key="tarjeta.nombre" class="stat-card">
          <div class="row justify-between items-center"><span>{{ tarjeta.nombre }}</span><q-icon :name="tarjeta.icono" color="primary" size="23px" /></div>
          <strong class="importe-resumen">{{ dinero(tarjeta.valor) }}</strong><small>{{ tarjeta.nota }}</small>
        </div>
      </div>
      <q-banner v-if="datos.proximas[0]" rounded class="bg-cyan-1 text-cyan-10 q-mb-lg">
        <strong>{{ datos.proximas[0].inicio <= datos.ahora ? 'Alquiler en curso' : 'Próximo alquiler' }}</strong>
        <div>{{ datos.proximas[0].cliente }} · {{ horario(datos.proximas[0].inicio) }} — {{ horario(datos.proximas[0].fin) }}</div>
        <template #action><q-btn flat no-caps label="Ver pagos" color="primary" :to="{ path: '/pagos', query: { reserva: String(datos.proximas[0].id) } }" /></template>
      </q-banner>
      <div class="row q-gutter-sm q-mb-lg">
        <q-btn outline no-caps color="primary" icon="event_available" label="Ver horarios libres" to="/agenda" />
        <q-btn outline no-caps color="primary" icon="account_balance_wallet" label="Caja de hoy" to="/caja" />
        <q-btn outline no-caps color="primary" icon="payments" label="Gestionar saldos" to="/pagos" />
      </div>
      <div class="dashboard-grid">
        <section class="panel">
          <div class="panel-heading">
            <div><h2>Agenda de la cancha</h2><p>Horarios del local · {{ datos.zonaHoraria }}</p></div>
            <q-btn flat no-caps color="primary" label="Ver todas" icon-right="arrow_forward" to="/reservas" />
          </div>
          <q-tabs v-model="agenda" dense active-color="primary" indicator-color="primary" align="left" no-caps>
            <q-tab name="hoy" label="Hoy" /><q-tab name="proximas" label="Próximas" />
          </q-tabs>
          <div v-if="!datos[agenda].length" class="empty-state"><q-icon name="event_available" size="44px" /><h3>Sin reservas en esta vista</h3><p>Los alquileres aparecerán aquí al programarlos.</p></div>
          <q-list v-else separator>
            <q-item v-for="reserva in datos[agenda]" :key="reserva.id" class="agenda-row">
              <q-item-section avatar><q-avatar class="event-avatar" icon="event" /></q-item-section>
              <q-item-section><q-item-label>{{ reserva.cliente }}</q-item-label><q-item-label caption>{{ horario(reserva.inicio) }} — {{ horario(reserva.fin) }}</q-item-label><q-item-label caption>{{ estadoPago(reserva).label }} · Abonado {{ dinero(reserva.abonado) }}</q-item-label></q-item-section>
              <q-item-section side><q-badge outline :color="colores[reserva.estado]" :label="estados[reserva.estado]" /></q-item-section>
            </q-item>
          </q-list>
        </section>
        <section class="panel">
          <div class="panel-heading"><div><h2>{{ datos.cancha.nombre }}</h2><p>Fútbol · Césped sintético</p></div><q-icon name="sports_soccer" color="primary" size="27px" /></div>
          <div class="q-pa-lg">
            <p class="text-grey-7 q-mb-xs">Tarifa por hora</p>
            <div class="text-h5 text-weight-medium">{{ datos.cancha.tarifa ? dinero(datos.cancha.tarifa) : 'Por configurar' }}</div>
            <q-btn flat no-caps color="primary" label="Configurar cancha" icon-right="arrow_forward" to="/cancha" class="q-mt-md" />
          </div>
        </section>
      </div>
    </template>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, colores, dinero, estados, horario, type Cancha, type Reserva } from '@/services/api';
import { estadoPago, type Totales } from '@/services/finanzas';

type Resumen = {
  cancha: Cancha; clientes: number; totalHoy: number; totalProximas: number;
  hoy: Reserva[]; proximas: Reserva[]; zonaHoraria: string; ahora: string;
  cajaHoy: Totales; saldoPendiente: string; demostracion: boolean;
};
const datos = ref<Resumen>();
const agenda = ref<'hoy' | 'proximas'>('hoy');
const error = ref('');
const cargando = ref(true);
const tarjetas = computed(() => {
  if (!datos.value) return [];
  return [
    { nombre: 'Reservas de hoy', valor: datos.value.totalHoy, nota: 'Programadas para hoy', icono: 'calendar_today', color: 'bg-cyan-1 text-cyan-9' },
    { nombre: 'Próximas reservas', valor: datos.value.totalProximas, nota: 'Confirmadas y sin finalizar', icono: 'schedule', color: 'bg-orange-1 text-orange-9' },
    { nombre: 'Mi cancha', valor: datos.value.cancha.activa ? 'Activa' : 'Inactiva', nota: 'Césped sintético', icono: 'sports_soccer', color: 'bg-green-1 text-green-9' },
    { nombre: 'Clientes', valor: datos.value.clientes, nota: 'Registrados', icono: 'groups', color: 'bg-purple-1 text-purple-8' },
  ];
});
const tarjetasFinanzas = computed(() => datos.value ? [
  { nombre: 'Cobros de hoy', valor: datos.value.cajaHoy.ingresos, nota: 'Dinero recibido por todos los métodos', icono: 'payments' },
  { nombre: 'Gastos de hoy', valor: datos.value.cajaHoy.gastos, nota: 'Gastos vigentes del día', icono: 'receipt_long' },
  { nombre: 'Neto de hoy', valor: datos.value.cajaHoy.neto, nota: 'Después de gastos y devoluciones', icono: 'account_balance_wallet' },
  { nombre: 'Saldo por cobrar', valor: datos.value.saldoPendiente, nota: 'Reservas confirmadas y completadas', icono: 'pending_actions' },
] : []);
async function cargar() {
  cargando.value = true;
  error.value = '';
  try { datos.value = await api<Resumen>('/resumen'); }
  catch (problema) { error.value = (problema as Error).message; }
  finally { cargando.value = false; }
}
onMounted(cargar);
</script>

<style scoped>
.importe-resumen { font-size: clamp(20px, 2vw, 30px); overflow-wrap: anywhere; }
</style>
