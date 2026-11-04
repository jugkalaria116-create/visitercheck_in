import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const apiTarget = env.VITE_DEV_API_TARGET || 'http://localhost:8000';
  return {
    plugins: [react()],
    server: {
      host: true,
      port: 5173,
      // /api is proxied so cookies, CSRF and image URLs stay same-origin in development
      proxy: { '/api': { target: apiTarget, changeOrigin: false } },
    },
    build: {
      // The PHP front controller serves the compiled app from backend/public
      outDir: '../backend/public',
      emptyOutDir: false,
      sourcemap: false,
    },
  };
});
