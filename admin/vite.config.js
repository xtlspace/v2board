import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// 构建产物输出到后端 public/assets/admin-next。
export default defineConfig({
  base: '/assets/admin-next/',
  plugins: [react()],
  build: {
    outDir: '../public/assets/admin-next',
    emptyOutDir: true,
    manifest: true,
    chunkSizeWarningLimit: 4096
  },
  server: {
    port: 5173,
    host: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true
      }
    }
  }
})
