import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite' // <--- Importa loadEnv
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import { quasar, transformAssetUrls } from '@quasar/vite-plugin'
import basicSsl from '@vitejs/plugin-basic-ssl'

export default defineConfig(({ mode }) => {

  const env = loadEnv(mode, process.cwd(), '');
  const myIp = env.VITE_SERVER_IP || 'localhost';
  const myPort = 8031;

  return {
    base: '/',
    plugins: [
      vue({ template: { transformAssetUrls } }),
      vueDevTools(),
      quasar({
        sassVariables: fileURLToPath(
          new URL('./src/resources/plugins/quasar/quasar-variables.sass', import.meta.url)
        ),
      }),
      basicSsl()
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src/resources', import.meta.url)),
        vue: 'vue/dist/vue.esm-bundler.js',
      },
    },
    server: {
      host: '0.0.0.0',
      port: myPort,
      strictPort: true,
      hmr: {
        host: myIp 
      },
      origin: `https://${myIp}:${myPort}`,
      cors: true,
    },
  }
})