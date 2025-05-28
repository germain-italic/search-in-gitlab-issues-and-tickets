import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  optimizeDeps: {
    exclude: ['lucide-react'],
  },
  base: '/', // Ensure assets are loaded from the correct path
  build: {
    outDir: 'dist', // Output directory for the build
    emptyOutDir: true, // Clean the output directory before build
    sourcemap: false, // Disable sourcemaps in production
  },
});