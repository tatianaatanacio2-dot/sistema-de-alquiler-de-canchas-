<template><router-view /></template>

<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { session } from '@/services/api';

const router = useRouter();
watch(() => session.usuario, usuario => {
  if (!usuario && !router.currentRoute.value.meta.public) void router.replace('/ingresar');
});
</script>
