<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="topbar">
      <q-toolbar class="q-px-md">
        <q-btn flat round icon="menu" color="grey-8" aria-label="Mostrar u ocultar menú" @click="drawer = !drawer" />
        <q-toolbar-title class="text-body2 text-grey-7">Gestión deportiva<span class="gt-sm"> / {{ route.meta.title }}</span></q-toolbar-title>
        <span class="today gt-sm">{{ hoy }}</span>
        <q-separator vertical inset class="q-mx-md" />
        <q-avatar size="36px" class="user-avatar">{{ session.usuario?.nombre.slice(0, 2).toUpperCase() }}</q-avatar>
        <div class="q-ml-sm gt-xs"><strong>{{ session.usuario?.nombre }}</strong><small class="block text-grey-6">Administración</small></div>
      </q-toolbar>
    </q-header>
    <q-drawer v-model="drawer" show-if-above :width="252" class="sidebar">
      <router-link to="/" class="brand"><div class="brand-icon"><q-icon name="sports_soccer" size="27px" /></div><div>Canchas<span>Alquiler deportivo</span></div></router-link>
      <div class="nav-label">ESPACIO DE TRABAJO</div>
      <q-list class="nav-list">
        <q-item v-for="item in navegacion" :key="item.ruta" clickable :to="item.ruta" exact active-class="nav-active">
          <q-item-section avatar><q-icon :name="item.icono" size="21px" /></q-item-section>
          <q-item-section>{{ item.nombre }}</q-item-section>
        </q-item>
      </q-list>
      <div class="sidebar-bottom">
        <div class="care-note"><q-icon name="sports_soccer" size="25px" /><strong>Cancha de fútbol</strong><p>Alquiler de césped sintético.</p></div>
        <q-btn flat no-caps class="full-width" icon="logout" label="Cerrar sesión" @click="salir" />
      </div>
    </q-drawer>
    <q-page-container><router-view /></q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, cerrarSesion, session } from '@/services/api';

const drawer = ref(false);
const route = useRoute();
const router = useRouter();
const hoy = new Intl.DateTimeFormat('es-PE', { dateStyle: 'long', timeZone: 'America/Bogota' }).format(new Date());
const navegacion = [
  { ruta: '/', nombre: 'Resumen general', icono: 'space_dashboard' },
  { ruta: '/cancha', nombre: 'Mi cancha', icono: 'sports_soccer' },
  { ruta: '/clientes', nombre: 'Clientes', icono: 'people_outline' },
  { ruta: '/reservas', nombre: 'Reservas', icono: 'calendar_month' },
];

async function salir() {
  try { await api('/salir', 'POST'); }
  finally {
    cerrarSesion();
    await router.replace('/ingresar');
  }
}
</script>
