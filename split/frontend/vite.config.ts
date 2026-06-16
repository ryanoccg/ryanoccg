import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Dedicated subdomain docroot → base '/'. During dev, proxy /api to the local
// PHP server (php -S localhost:8000 -t ../api) so the SPA is same-origin.
export default defineConfig({
  plugins: [react()],
  base: '/',
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    target: 'es2018',
  },
})
