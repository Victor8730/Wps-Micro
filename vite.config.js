import { defineConfig, loadEnv } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const backendOrigin = env.VITE_BACKEND_ORIGIN || env.APP_URL || 'http://localhost';

  return {
    plugins: [tailwindcss()],
    publicDir: false,
    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      cors: {
        origin: backendOrigin,
      },
    },
    build: {
      manifest: true,
      outDir: 'public/build',
      emptyOutDir: true,
      rollupOptions: {
        input: 'resources/js/app.js',
      },
    },
  };
});
