import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    target: ['es2020', 'chrome80', 'firefox80', 'safari14', 'edge80'],
    outDir: '../assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'src/main.tsx'),
      },
    },
  },
  server: {
    port: 3002,
    strictPort: true,
    cors: true,
    proxy: {
      '/wp-json': {
        target: 'http://localhost:8888',
        changeOrigin: true,
      },
    },
  },
});
