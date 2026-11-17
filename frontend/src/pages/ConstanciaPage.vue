<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div><span class="eyebrow">DETALLE DEL ALQUILER</span><h1>Constancia de reserva</h1></div>
      <div class="row q-gutter-sm">
        <q-btn flat no-caps color="primary" label="Volver a reservas" icon="arrow_back" to="/reservas" />
        <q-btn color="primary" unelevated no-caps label="Imprimir / guardar PDF" icon="print" :disable="!datos || cargando" @click="imprimir" />
      </div>
    </div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-lg" role="alert">{{ error }}</q-banner>
    <q-inner-loading :showing="cargando" />
    <article v-if="datos" class="panel constancia q-pa-lg">
      <div class="row justify-between items-start q-gutter-md q-mb-lg">
        <div><h2>{{ datos.cancha.nombre }}</h2><p class="text-grey-7">Cancha de fútbol · Césped sintético</p></div>
        <div class="text-right"><strong>Reserva #{{ datos.reserva.id }}</strong><div>{{ estados[datos.reserva.estado] }}</div><div class="text-caption">{{ horario(datos.emitido) }} · Bogotá</div></div>
      </div>
      <q-separator />
      <dl class="detalle q-my-lg">
        <div><dt>Cliente</dt><dd>{{ datos.reserva.cliente }}</dd></div>
        <div v-if="datos.cliente?.dni"><dt>DNI</dt><dd>{{ datos.cliente.dni }}</dd></div>
        <div v-if="datos.cliente?.telefono"><dt>Teléfono</dt><dd>{{ datos.cliente.telefono }}</dd></div>
        <div><dt>Inicio</dt><dd>{{ horario(datos.reserva.inicio) }}</dd></div>
        <div><dt>Fin</dt><dd>{{ horario(datos.reserva.fin) }}</dd></div>
        <div><dt>Duración</dt><dd>{{ duracion }} minutos</dd></div>
        <div><dt>Tarifa por hora</dt><dd>{{ dinero(datos.reserva.tarifa) }}</dd></div>
        <div><dt>Total de la reserva</dt><dd>{{ dinero(datos.reserva.total) }}</dd></div>
      </dl>
      <q-separator />
      <div class="row q-col-gutter-lg q-my-md">
        <div class="col-12 col-sm-4"><span class="text-grey-7">Estado del pago</span><div class="text-weight-medium">{{ estadoPago(datos.reserva).label }}</div></div>
        <div class="col-12 col-sm-4"><span class="text-grey-7">Abonado neto</span><div class="text-weight-medium">{{ dinero(datos.reserva.abonado) }}</div></div>
        <div class="col-12 col-sm-4"><span class="text-grey-7">{{ datos.reserva.estado === 'cancelada' ? 'Por devolver' : 'Saldo pendiente' }}</span><div class="text-weight-medium">{{ dinero(saldoReserva(datos.reserva)) }}</div></div>
      </div>
      <h2 class="q-mt-lg q-mb-md">Pagos y devoluciones registrados</h2>
      <q-markup-table v-if="datos.pagos.length" flat wrap-cells>
        <thead><tr><th class="text-left">Fecha</th><th class="text-left">Movimiento / método</th><th class="text-left">Referencia o nota</th><th class="text-right">Importe</th></tr></thead>
        <tbody><tr v-for="pago in datos.pagos" :key="pago.id">
          <td>{{ horario(pago.fecha.replace(' ', 'T')) }}</td><td>{{ pago.tipo === 'cobro' ? 'Cobro' : 'Devolución' }} · {{ nombreMetodo(pago.metodo) }}</td><td>{{ pago.nota || '—' }}</td><td class="text-right">{{ dinero(pago.monto) }}</td>
        </tr></tbody>
      </q-markup-table>
      <p v-else class="text-grey-7">Todavía no hay pagos registrados.</p>
    </article>
  </q-page>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api, dinero, estados, horario, type Cancha, type Cliente, type Reserva } from '@/services/api';
import { estadoPago, nombreMetodo, saldoReserva, type Pago } from '@/services/finanzas';

type Detalle = { reserva: Reserva; cancha: Cancha; cliente: Cliente | null; pagos: Pago[]; emitido: string };
const route = useRoute();
const datos = ref<Detalle>();
const error = ref('');
const cargando = ref(false);
const duracion = computed(() => datos.value
  ? (Date.parse(datos.value.reserva.fin + 'Z') - Date.parse(datos.value.reserva.inicio + 'Z')) / 60000 : 0);
const imprimir = () => window.print();
let secuencia = 0;
async function cargar() {
  const actual = ++secuencia;
  datos.value = undefined;
  cargando.value = true;
  error.value = '';
  try {
    const resultado = await api<Detalle>('/reservas/' + encodeURIComponent(String(route.params.id)));
    if (actual === secuencia) datos.value = resultado;
  } catch (problema) { if (actual === secuencia) error.value = (problema as Error).message; }
  finally { if (actual === secuencia) cargando.value = false; }
}
watch(() => route.params.id, cargar, { immediate: true });
</script>

<style scoped>
.constancia { max-width: 950px; margin: 0 auto; }
.detalle { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
dt { color: #61746e; font-size: 12px; }
dd { margin: 4px 0 0; overflow-wrap: anywhere; }
@media (max-width: 600px) { .detalle { grid-template-columns: 1fr; } }
@media print {
  .q-page { min-height: auto !important; }
  .constancia { border: 0; max-width: none; padding: 12px; }
  .detalle { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  tr, .detalle > div { break-inside: avoid; }
}
</style>
