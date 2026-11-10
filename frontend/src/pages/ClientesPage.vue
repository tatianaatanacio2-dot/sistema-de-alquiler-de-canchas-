<template>
  <q-page class="workspace">
    <div class="page-heading">
      <div><span class="eyebrow">GESTIÓN DE ALQUILERES</span><h1>Clientes</h1><p>Registra los datos de quienes reservan la cancha.</p></div>
      <q-btn color="primary" unelevated no-caps icon="add" label="Nuevo cliente" @click="editar()" />
    </div>
    <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
    <section class="panel">
      <div class="table-toolbar">
        <q-input v-model="busqueda" class="table-search" outlined dense clearable placeholder="Buscar clientes…" aria-label="Buscar clientes">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <q-btn flat round dense icon="refresh" aria-label="Actualizar clientes" @click="cargar" />
      </div>
      <q-table flat class="data-table" :rows="clientes" :columns="columnas" :filter="busqueda || ''" :loading="cargando"
        row-key="id" :pagination="{ rowsPerPage: 10 }" no-data-label="Aún no hay clientes para mostrar" no-results-label="No se encontraron coincidencias">
        <template #body-cell-acciones="props">
          <q-td :props="props"><q-btn flat dense no-caps color="primary" icon="edit" label="Editar" @click="editar(props.row)" /></q-td>
        </template>
      </q-table>
    </section>
    <q-dialog v-model="abierto" :persistent="guardando">
      <q-card class="dialog-card">
        <q-card-section><h2>{{ datos.id ? 'Editar cliente' : 'Nuevo cliente' }}</h2></q-card-section>
        <q-separator />
        <q-form @submit="guardar">
          <q-card-section class="form-grid">
            <q-banner v-if="errorFormulario" rounded class="full-field bg-red-1 text-red-9" role="alert">{{ errorFormulario }}</q-banner>
            <q-input v-model="datos.dni" outlined label="DNI (opcional)" inputmode="numeric" maxlength="20" />
            <q-input v-model="datos.nombre" outlined label="Nombre y apellido (opcional)" maxlength="120" />
            <q-input v-model="datos.telefono" outlined label="Teléfono (opcional)" type="tel" maxlength="30" />
            <q-input v-model="datos.correo" outlined label="Correo electrónico (opcional)" type="email" maxlength="254" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cerrar" v-close-popup :disable="guardando" />
            <q-btn color="primary" unelevated no-caps label="Guardar cliente" type="submit" :loading="guardando" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useQuasar, type QTableColumn } from 'quasar';
import { api, type Cliente } from '@/services/api';

const q = useQuasar();
const clientes = ref<Cliente[]>([]);
const busqueda = ref('');
const error = ref('');
const errorFormulario = ref('');
const cargando = ref(false);
const abierto = ref(false);
const guardando = ref(false);
const clienteVacio: Cliente = { id: 0, dni: '', nombre: '', telefono: '', correo: '' };
const datos = ref({ ...clienteVacio });
const columnas: QTableColumn<Cliente>[] = [
  { name: 'nombre', label: 'Nombre y apellido', field: 'nombre', format: valor => valor || '—', align: 'left', sortable: true },
  { name: 'dni', label: 'DNI', field: 'dni', format: valor => valor || '—', align: 'left' },
  { name: 'telefono', label: 'Teléfono', field: 'telefono', format: valor => valor || '—', align: 'left' },
  { name: 'correo', label: 'Correo electrónico', field: 'correo', format: valor => valor || '—', align: 'left' },
  { name: 'acciones', label: 'Acciones', field: 'id', align: 'right' },
];

async function cargar() {
  cargando.value = true;
  error.value = '';
  try { clientes.value = await api<Cliente[]>('/clientes'); }
  catch (problema) { error.value = (problema as Error).message; }
  finally { cargando.value = false; }
}
function editar(cliente?: Cliente) {
  datos.value = { ...(cliente ?? clienteVacio) };
  errorFormulario.value = '';
  abierto.value = true;
}
async function guardar() {
  guardando.value = true;
  errorFormulario.value = '';
  const id = datos.value.id;
  try {
    await api(id ? '/clientes/' + id : '/clientes', id ? 'PUT' : 'POST', datos.value);
    abierto.value = false;
    q.notify({ type: 'positive', message: 'Cliente guardado.' });
    await cargar();
  } catch (problema) { errorFormulario.value = (problema as Error).message; }
  finally { guardando.value = false; }
}
onMounted(cargar);
</script>
