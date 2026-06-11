import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

/**
 * Bookora build config.
 *
 * Builds the wp-admin React SPA into `assets/build/` with stable, predictable
 * filenames so PHP can enqueue them without parsing a manifest (Stage 1).
 */
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, 'assets/build'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'assets/src/admin/main.tsx'),
        frontend: resolve(__dirname, 'assets/src/frontend/main.tsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name].[ext]',
      },
    },
  },
});
