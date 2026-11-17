<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="topbar">
      <q-toolbar>
        <q-btn v-if="!movil" flat round icon="menu" aria-label="Mostrar u ocultar menú" @click="drawer = !drawer" />
        <div v-else class="brand-icon"><q-icon name="sports_soccer" size="21px" /></div>
        <q-toolbar-title>{{ movil ? 'Canchas' : route.meta.title }}</q-toolbar-title>
        <span v-if="!movil" class="today">{{ hoy }}</span>
        <q-btn flat round aria-label="Menú de cuenta" class="account-button">
          <q-avatar size="34px" class="user-avatar">{{ session.usuario?.nombre.slice(0, 2).toUpperCase() }}</q-avatar>
          <q-menu anchor="bottom right" self="top right" :offset="[0, 8]">
            <q-list class="account-menu">
              <q-item><q-item-section><q-item-label>{{ session.usuario?.nombre }}</q-item-label><q-item-label caption>Administración</q-item-label></q-item-section></q-item>
              <q-separator />
              <q-item clickable v-close-popup @click="salir">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>Cerrar sesión</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>
    <q-drawer v-if="!movil" v-model="drawer" show-if-above :breakpoint="1023" :width="230" class="sidebar">
      <router-link to="/" class="brand"><div class="brand-icon"><q-icon name="sports_soccer" size="27px" /></div><div>Canchas<span>Alquiler deportivo</span></div></router-link>
      <q-list class="nav-list">
        <q-item v-for="item in navegacion" :key="item.ruta" clickable :to="item.ruta" exact active-class="nav-active">
          <q-item-section avatar><q-icon :name="item.icono" size="21px" /></q-item-section>
          <q-item-section>{{ item.nombre }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>
    <q-page-container><router-view /></q-page-container>
    <q-footer v-if="movil" class="bottom-nav">
      <q-tabs no-caps align="justify" active-color="primary" indicator-color="transparent" aria-label="Navegación principal">
        <q-route-tab v-for="item in navegacion" :key="item.ruta" :to="item.ruta" exact :icon="item.icono" :label="item.nombre" />
      </q-tabs>
    </q-footer>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQuasar } from 'quasar';
import { useRoute, useRouter } from 'vue-router';
import { api, cerrarSesion, session } from '@/services/api';

const drawer = ref(false);
const q = useQuasar();
const movil = computed(() => q.screen.lt.md);
const route = useRoute();
const router = useRouter();
const hoy = new Intl.DateTimeFormat('es-PE', { dateStyle: 'long', timeZone: 'America/Bogota' }).format(new Date());
const navegacion = [
  { ruta: '/', nombre: 'Resumen', icono: 'space_dashboard' },
  { ruta: '/reservas', nombre: 'Reservas', icono: 'calendar_month' },
  { ruta: '/clientes', nombre: 'Clientes', icono: 'people_outline' },
  { ruta: '/cancha', nombre: 'Cancha', icono: 'sports_soccer' },
];

async function salir() {
  try { await api('/salir', 'POST'); }
  finally {
    cerrarSesion();
    await router.replace('/ingresar');
  }
}
</script>
