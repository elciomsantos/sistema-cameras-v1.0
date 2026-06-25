import { defineConfig } from 'vite';
import compression from 'vite-plugin-compression';
import { resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  root: 'src',
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  publicDir: '../public',
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    copyPublicDir: false,
    cssCodeSplit: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: './index.html',
        'manutencao-cameras': './assets/js/entries/manutencao-cameras.ts',
        'manutencao-alarmes': './assets/js/entries/manutencao-alarmes.ts',
        'home-dashboard': './assets/js/entries/home-dashboard.ts',
        'cadastro-cameras': './assets/js/entries/cadastro-cameras.ts',
        'editar-cameras': './assets/js/entries/editar-cameras.ts',
        'listar-cameras': './assets/js/entries/listar-cameras.ts',
        'listar-alarmes': './assets/js/entries/listar-alarmes.ts',
        'relatorios-cameras': './assets/js/entries/relatorios-cameras.ts',
        'relatorios-alarmes': './assets/js/entries/relatorios-alarmes.ts',
        'auditoria-cameras': './assets/js/entries/auditoria-cameras.ts',
        'cadastro-alarmes': './assets/js/entries/cadastro-alarmes.ts',
        'editar-alarmes': './assets/js/entries/editar-alarmes.ts',
        'cadastro-locais': './assets/js/entries/cadastro-locais.ts',
        'editar-locais': './assets/js/entries/editar-locais.ts',
      },
      output: {
        assetFileNames: 'assets/[name]-[hash][extname]',
        entryFileNames: 'assets/js/[name]-[hash].js',
        chunkFileNames: 'assets/js/[name]-[hash].js',
        manualChunks(id: string) {
          if (id.includes('ui-utils') || id.includes('ErrorHandler') || id.includes('LoadingManager') || id.includes('html.ts')) return 'vendor-ui';
          if (id.includes('file-upload')) return 'vendor-upload';
          if (id.includes('fetchWithTimeout')) return 'vendor-fetch';
        },
      },
    },
    sourcemap: 'hidden',
    minify: 'esbuild',
  },
  server: {
    port: 3000,
    open: false,
    cors: true,
    proxy: {
      '/api': {
        target: process.env.VITE_API_URL || 'http://localhost:80',
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
