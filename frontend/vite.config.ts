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
    // Tailwind CSS 4 requires Safari 16.4+, Chrome/Edge 111+, Firefox 128+
    // (cascade layers, @property, color-mix()). Keep these in sync with the
    // browserslist in package.json.
    target: ['es2022', 'chrome111', 'edge111', 'firefox128', 'safari16.4'],
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
