import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// Dev proxy target for /api and /storage:
// - VITE_PROXY_TARGET takes precedence (set to http://backend:8000 under Docker,
//   where "localhost" inside the frontend container cannot reach the Laravel service).
// - Falls back to VITE_API_URL so a standalone :5173 dev run can still point at a
//   specific backend.
// - Final fallback: http://localhost:8000 (plain host-machine dev server).
const apiTarget = process.env.VITE_PROXY_TARGET || process.env.VITE_API_URL || 'http://localhost:8000'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss()
  ],
  server: {
    host: '0.0.0.0',
    port: 5173,
    watch: {
      usePolling: true,
    },
    hmr: {
      host: 'localhost',
      port: 5173,
    },
    // Dev-only proxy: lets Vite reach the Laravel backend when the app is
    // accessed directly on :5173 without the Nginx reverse proxy. In the
    // Docker/Nginx runtime the browser calls /api on the same origin (or the
    // absolute VITE_API_URL from docker-compose env), so this is a fallback.
    proxy: {
      '/api': {
        target: apiTarget.replace(/\/+$/, ''),
        changeOrigin: true,
      },
      '/storage': {
        target: apiTarget.replace(/\/+$/, ''),
        changeOrigin: true,
      },
    },
  },
})