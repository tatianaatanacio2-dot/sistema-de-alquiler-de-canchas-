import { defineCapacitorConfig } from '@quasar/app-vite/capacitor';

export default defineCapacitorConfig({
  appId: 'pe.alquilercancha.app',
  appName: 'Alquiler de cancha',
  webDir: 'www',
  plugins: { CapacitorHttp: { enabled: true } },
});
