<template>
  <q-page class="workspace">
    <div class="page-heading"><div><span class="eyebrow">GESTIÓN DE ALQUILERES</span><h1>Mi cancha</h1><p>Configura la única cancha de fútbol de césped sintético.</p></div></div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
    <q-inner-loading :showing="cargando" />
    <section v-if="cancha" class="panel">
      <div class="panel-heading"><div><h2>Configuración de la cancha</h2><p>Tarifa en soles y disponibilidad para nuevos alquileres.</p></div><q-icon name="sports_soccer" color="primary" size="27px" /></div>
      <q-form class="q-pa-lg form-grid" @submit="guardar">
        <q-input v-model="cancha.nombre" outlined label="Nombre de la cancha" maxlength="100" :rules="[v => !!v || 'Ingresa un nombre']" />
        <q-input v-model.number="cancha.tarifa" outlined type="number" min="1" max="100000000" step="1" prefix="S/" label="Tarifa por hora" :rules="[v => Number.isInteger(Number(v)) && Number(v) > 0 || 'Ingresa una tarifa en soles enteros']" />
        <q-toggle v-model="cancha.activa" :true-value="1" :false-value="0" color="primary" label="Disponible para alquiler" />
        <div class="full-field row justify-end"><q-btn color="primary" unelevated no-caps type="submit" label="Guardar cambios" :loading="guardando" /></div>
      </q-form>
    </section>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useQuasar } from 'quasar';
import { api, type Cancha } from '@/services/api';

const q = useQuasar();
const cancha = ref<Cancha>();
const cargando = ref(true);
const guardando = ref(false);
const error = ref('');
onMounted(async () => {
  try { cancha.value = await api<Cancha>('/cancha'); }
  catch (problema) { error.value = (problema as Error).message; }
  finally { cargando.value = false; }
});
async function guardar() {
  if (!cancha.value) return;
  guardando.value = true;
  error.value = '';
  try {
    cancha.value = await api<Cancha>('/cancha', 'PUT', cancha.value);
    q.notify({ type: 'positive', message: 'Configuración guardada.' });
  } catch (problema) { error.value = (problema as Error).message; }
  finally { guardando.value = false; }
}
</script>
