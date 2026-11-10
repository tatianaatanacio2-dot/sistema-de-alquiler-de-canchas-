import { defineConfig } from '#q-app';

export default defineConfig(() => ({
  css: ['app.scss'],
  extras: ['roboto-font', 'material-icons'],
  build: { vueRouterMode: 'hash', publicPath: './' },
  devServer: {
    open: false,
    port: 9012,
    proxy: { '/api': { target: 'http://127.0.0.1:8012', changeOrigin: true } },
  },
  framework: { lang: 'es', plugins: ['Dialog', 'Notify'] },
}));
