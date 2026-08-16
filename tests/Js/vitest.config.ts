import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['tests/Js/**/*.test.js', 'tests/Js/**/*.test.ts'],
    coverage: {
      reporter: ['text', 'clover', 'html'],
      include: ['src/assets/js/**/*.js', 'src/assets/js/**/*.ts'],
    },
  },
});
