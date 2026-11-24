<template>
  <main class="login-page">
    <section class="login-story">
      <div class="brand"><div class="brand-icon"><q-icon name="sports_soccer" size="25px" /></div><div>Canchas<span>Alquiler deportivo</span></div></div>
    </section>
    <section class="login-panel">
      <div class="login-box">
        <h1>{{ acceso?.requiereCuenta && acceso.equipoLocal ? 'Crea tu cuenta' : 'Inicia sesión' }}</h1>
        <p class="login-description">Accede a la agenda y administra las reservas de tu cancha.</p>
        <q-form v-if="android" class="server-form" @submit="consultar">
            <q-input v-model="direccion" outlined label="Servidor" autocomplete="url"
              inputmode="url" :disable="conectando || ocupado" />
            <q-btn outline color="primary" label="Conectar" no-caps type="submit" :loading="conectando" :disable="ocupado" />
        </q-form>
        <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
        <q-banner v-if="acceso?.requiereCuenta && !acceso.equipoLocal" rounded class="bg-cyan-1 text-cyan-9">
          Crea primero la cuenta en la computadora del local.
        </q-banner>
        <q-form v-else-if="acceso" class="credentials-form" @submit="entrar">
          <q-input v-if="acceso.requiereCuenta" v-model="datos.nombre" outlined label="Nombre" maxlength="80" autocomplete="name" :rules="[v => !!v || 'Ingresa tu nombre']" />
          <q-input v-model="datos.correo" outlined label="Correo electrónico" type="email" maxlength="254" autocomplete="username" :rules="[v => !!v || 'Ingresa tu correo']" />
          <q-input v-model="datos.clave" outlined label="Contraseña" :type="visible ? 'text' : 'password'" maxlength="72"
            :autocomplete="acceso.requiereCuenta ? 'new-password' : 'current-password'"
            :rules="[v => !!v || 'Ingresa tu contraseña', v => !acceso?.requiereCuenta || v.length >= 8 || 'Usa al menos 8 caracteres']">
            <template #append>
              <q-btn flat round dense :icon="visible ? 'visibility_off' : 'visibility'"
                :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="visible = !visible" />
            </template>
          </q-input>
          <q-btn unelevated no-caps color="primary" class="full-width" size="lg" type="submit" :loading="ocupado"
            :label="acceso.requiereCuenta ? 'Crear cuenta e ingresar' : 'Ingresar al sistema'" />
        </q-form>
        <q-btn v-else-if="!android && !conectando" flat no-caps color="primary" label="Reintentar" @click="consultar" />
        <q-inner-loading :showing="conectando && !android" />
      </div>
    </section>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { android, api, conectar, ErrorApi, ingresar, servidor, type Acceso } from '@/services/api';

const router = useRouter();
const direccion = ref(servidor.value);
const estadoAcceso = ref<Acceso>();
const acceso = computed(() => (!android || direccion.value === servidor.value) ? estadoAcceso.value : undefined);
const error = ref('');
const visible = ref(false);
const ocupado = ref(false);
const conectando = ref(false);
const datos = reactive({ nombre: '', correo: '', clave: '' });

async function consultar() {
  if (conectando.value || ocupado.value) return;
  conectando.value = true;
  estadoAcceso.value = undefined;
  error.value = '';
  try {
    if (android) {
      const anterior = servidor.value;
      estadoAcceso.value = await conectar(direccion.value);
      direccion.value = servidor.value;
      if (servidor.value !== anterior) datos.clave = '';
    } else {
      estadoAcceso.value = await api<Acceso>('/acceso');
    }
  } catch (problema) {
    error.value = problema instanceof Error ? problema.message : 'No se pudo conectar.';
  } finally {
    conectando.value = false;
  }
}

async function entrar() {
  if (!acceso.value || ocupado.value || conectando.value) return;
  const configurando = acceso.value.requiereCuenta;
  ocupado.value = true;
  error.value = '';
  try {
    await ingresar(datos, configurando);
    await router.replace('/');
  } catch (problema) {
    if (configurando && problema instanceof ErrorApi && problema.estado === 409) {
      ocupado.value = false;
      datos.clave = '';
      await consultar();
      if (acceso.value && !acceso.value.requiereCuenta) {
        error.value = 'La cuenta ya está configurada. Ingresa con tus credenciales.';
      }
      return;
    }
    error.value = (problema as Error).message;
  } finally {
    ocupado.value = false;
  }
}

onMounted(() => {
  if (!android || direccion.value) void consultar();
});
</script>
