/// <reference types="vitest" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    allowedHosts: true,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.js',
    css: true,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'json-summary'],
      reportsDirectory: './coverage',
      // Le perimetre mesure est le code applicatif : ce que les tests
      // d'infrastructure (setup, point d'entree, configuration) traversent
      // n'est pas du comportement a couvrir.
      include: ['src/**/*.{js,jsx,ts,tsx}'],
      exclude: [
        'src/test/**',
        'src/main.jsx',
        'src/**/*.config.{js,ts}',
        'src/**/*.d.ts',
      ],
      // CLIQUET, pas cible.
      //
      // Premiere mesure reelle (2026-08-22) : 29,27 % de lignes, avant les
      // premiers tests d'integration MSW ; 30,80 % apres. L'exigence §2.1.3 du
      // memoire — 70 % — n'est donc PAS atteinte : les tests existants couvrent
      // les composants d'interface, presque aucune page.
      //
      // Les seuils sont cales juste sous le niveau mesure : ils empechent toute
      // regression sans bloquer la chaine sur un chiffre hors d'atteinte, qui
      // aurait ete desactive a la premiere occasion. Ils doivent etre releves a
      // chaque lot de tests ajoute, jusqu'aux valeurs de l'exigence.
      thresholds: {
        lines: 30,
        functions: 19,
        branches: 20,
        statements: 28,
      },
    },
  },
})
