import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite' // 1. Import loadEnv
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // 2. Load env variables based on mode (development/production)
  const env = loadEnv(mode, process.cwd(), '');

  return {
    plugins: [
      vue(),
      vueDevTools(),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url))
      },
    },
    server: {
      host: true,
      port: 5173,
      allowedHosts: [
        '.ngrok-free.dev', // Allow all ngrok subdomains
        '.vercel.app'      // Allow Vercel previews
      ],
      proxy: {
        '/api': {
          // 3. Use the variable from .env, fallback to localhost if missing
          target: env.VITE_NGROK_URL || 'http://localhost:8000',
          changeOrigin: true,
          secure: false,
        }
      }
    }
  }
})