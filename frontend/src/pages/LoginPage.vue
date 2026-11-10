<template>
  <main class="login-page">
    <section class="login-story">
      <div class="brand light"><div class="brand-icon"><q-icon name="sports_soccer" size="27px" /></div><div>Canchas<span>Alquiler deportivo</span></div></div>
      <div>
        <span class="eyebrow">TU PRÓXIMO PARTIDO</span>
        <h1>Cada partido<br>empieza con<br>una reserva.</h1>
        <p>Organiza los clientes y los horarios<br>de tu cancha de césped sintético.</p>
        <div class="story-mark"><q-icon name="sports_soccer" size="96px" /></div>
      </div>
      <small>Sistema de alquiler de cancha de fútbol</small>
    </section>
    <section class="login-panel">
      <div class="login-box">
        <span class="eyebrow text-primary">BIENVENIDO A CANCHAS</span>
        <h2>{{ acceso?.requiereCuenta && acceso.equipoLocal ? 'Crea tu cuenta' : 'Inicia sesión' }}</h2>
        <p class="text-grey-7 q-mb-lg">Accede al panel de administración y a la agenda de la cancha.</p>
        <q-form v-if="android" class="q-mb-md" @submit="consultar">
          <div class="row items-center q-gutter-sm no-wrap">
            <q-input v-model="direccion" class="col" dense outlined label="Servidor del local" type="url" />
            <q-btn flat color="primary" label="Conectar" no-caps type="submit" :loading="conectando" />
          </div>
        </q-form>
        <q-banner v-if="error" rounded class="bg-red-1 text-red-9 q-mb-md" role="alert">{{ error }}</q-banner>
        <q-banner v-if="acceso?.requiereCuenta && !acceso.equipoLocal" rounded class="bg-cyan-1 text-cyan-9">
          Crea primero la cuenta en la computadora del local.
        </q-banner>
        <q-form v-else-if="acceso" class="q-gutter-md" @submit="entrar">
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
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { android, api, conectar, ingresar, servidor, type Acceso } from '@/services/api';

const router = useRouter();
const direccion = ref(servidor.value);
const acceso = ref<Acceso>();
const error = ref('');
const visible = ref(false);
const ocupado = ref(false);
const conectando = ref(false);
const datos = reactive({ nombre: '', correo: '', clave: '' });

async function consultar() {
  conectando.value = true;
  acceso.value = undefined;
  error.value = '';
  try {
    if (android) conectar(direccion.value);
    acceso.value = await api<Acceso>('/acceso');
  } catch (problema) {
    error.value = problema instanceof Error ? problema.message : 'No se pudo conectar.';
  } finally {
    conectando.value = false;
  }
}

async function entrar() {
  ocupado.value = true;
  error.value = '';
  try {
    await ingresar(datos, Boolean(acceso.value?.requiereCuenta));
    await router.replace('/');
  } catch (problema) {
    error.value = (problema as Error).message;
  } finally {
    ocupado.value = false;
  }
}

onMounted(() => {
  if (!android || direccion.value) void consultar();
});
</script>
