import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    sourcemap: true,
    rollupOptions: {
      input: 'src/main.ts',
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (chunkInfo) => {
          if (chunkInfo.name && chunkInfo.name.endsWith('.css')) {
            return 'css/[name].css';
          }
          return 'js/[name]-[hash][extname]';
        }
      }
    }
  }
});
