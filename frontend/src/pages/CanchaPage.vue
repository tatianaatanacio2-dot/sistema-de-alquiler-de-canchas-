<template>
  <q-page class="workspace">
    <div class="page-heading"
      ><div
        ><span class="eyebrow">GESTIÓN DE ALQUILERES</span><h1>Canchas</h1
        ><p
          >Elige una cancha de césped sintético para configurar su nombre,
          tarifa y disponibilidad.</p
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
    <q-inner-loading :showing="cargando" />
    <section v-if="cancha" class="panel">
      <div class="panel-heading"
        ><h2>Configuración de la cancha</h2
        ><q-icon name="sports_soccer" color="primary" size="27px"
      /></div>
      <q-form class="panel-form form-grid" @submit="guardar">
        <q-select
          :model-value="cancha.id"
          :options="canchas"
          option-value="id"
          option-label="nombre"
          emit-value
          map-options
          outlined
          class="full-field"
          label="Cancha que deseas configurar"
          :disable="guardando"
          @update:model-value="seleccionar"
        />
        <q-input
          v-model="cancha.nombre"
          outlined
          label="Nombre de la cancha"
          maxlength="100"
          :rules="[v => !!v || 'Ingresa un nombre']"
        />
        <q-input
          v-model.number="cancha.tarifa"
          outlined
          type="number"
          min="1"
          max="100000000"
          step="1"
          prefix="S/"
          label="Tarifa por hora"
          :rules="[
            v =>
              (Number.isInteger(Number(v)) && Number(v) > 0) ||
              'Ingresa una tarifa en soles enteros'
          ]"
        />
        <q-toggle
          v-model="cancha.activa"
          :true-value="1"
          :false-value="0"
          color="primary"
          label="Disponible para alquiler"
        />
        <div class="full-field form-submit"
          ><q-btn
            color="primary"
            unelevated
            no-caps
            type="submit"
            label="Guardar cambios"
            :loading="guardando"
        /></div>
      </q-form>
    </section>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useQuasar } from "quasar";
import { api, type Cancha } from "@/services/api";

const q = useQuasar();
const canchas = ref<Cancha[]>([]);
const cancha = ref<Cancha>();
const cargando = ref(true);
const guardando = ref(false);
const error = ref("");
onMounted(async () => {
  try {
    canchas.value = await api<Cancha[]>("/canchas");
    if (canchas.value[0]) seleccionar(canchas.value[0].id);
  } catch (problema) {
    error.value = (problema as Error).message;
  } finally {
    cargando.value = false;
  }
});
function seleccionar(id: number) {
  const seleccionada = canchas.value.find(item => item.id === id);
  if (seleccionada)
    cancha.value = { ...seleccionada, activa: seleccionada.activa ? 1 : 0 };
  error.value = "";
}
async function guardar() {
  if (!cancha.value) return;
  guardando.value = true;
  error.value = "";
  try {
    const actualizada = await api<Cancha>("/cancha", "PUT", {
      ...cancha.value,
      cancha_id: cancha.value.id
    });
    canchas.value = canchas.value.map(item =>
      item.id === actualizada.id ? actualizada : item
    );
    seleccionar(actualizada.id);
    q.notify({ type: "positive", message: "Configuración guardada." });
  } catch (problema) {
    error.value = (problema as Error).message;
  } finally {
    guardando.value = false;
  }
}
</script>
