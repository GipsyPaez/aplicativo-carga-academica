import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  return {
    base: env.VITE_APP_BASE || '/',
    plugins: [react()],
    server: {
      proxy: {
        '/api': {
          target: 'http://127.0.0.1',
          changeOrigin: true,
          rewrite: path => path.replace(/^\/api/, '/carga-academica/api')
        }
      }
    }
  };
});
