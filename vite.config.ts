import { defineConfig } from 'vite';
import compression from 'vite-plugin-compression';

export default defineConfig({
  root: 'src',
  publicDir: '../public',
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    copyPublicDir: false,
    cssCodeSplit: false,
    manifest: true,
    rollupOptions: {
      input: {
        main: './index.html',
        'manutencao-cameras': './assets/js/entries/manutencao-cameras.ts',
        'manutencao-alarmes': './assets/js/entries/manutencao-alarmes.ts',
        'home-dashboard': './assets/js/entries/home-dashboard.ts',
        'cadastro-cameras': './assets/js/entries/cadastro-cameras.ts',
        'editar-cameras': './assets/js/entries/editar-cameras.ts',
      },
      output: {
        assetFileNames: 'assets/[name]-[hash][extname]',
        entryFileNames: 'assets/js/[name]-[hash].js',
        chunkFileNames: 'assets/js/[name]-[hash].js',
      },
    },
    sourcemap: false,
    minify: 'esbuild',
  },
  server: {
    port: 3000,
    open: false,
    cors: true,
    proxy: {
      '/api': {
        target: 'http://localhost:80',
        changeOrigin: true,
      },
    },
  },
  plugins: [
    compression({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 1024,
    }),
  ],
  esbuild: {
    target: 'es2020',
    legalComments: 'none',
  },
});
