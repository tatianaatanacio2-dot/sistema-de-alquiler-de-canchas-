import { defineCapacitorConfig } from '@quasar/app-vite/capacitor';

export default defineCapacitorConfig({
  appId: 'pe.alquilercancha.app',
  appName: 'Alquiler de cancha',
  webDir: 'www',
  // La API usa CapacitorHttp.request directamente.
  plugins: { CapacitorHttp: { enabled: false } },
});
