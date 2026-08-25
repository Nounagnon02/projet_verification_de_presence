/// <reference types="vitest" />
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

// Cible du proxy de developpement, surchargeable par VITE_BACKEND_URL.
//
// Elle etait figee sur le port 8000. Quand un autre projet Laravel occupait ce
// port, le proxy relayait vers LUI : l'interface recevait des 404 sur chaque
// route — « The route api/login could not be found » — sans que rien n'indique
// que le backend joint n'etait pas le bon.
export default defineConfig(({ mode }) => {
  // loadEnv plutot que process.env : la configuration Vite est evaluee dans un
  // contexte ou « process » n'est pas declare pour ESLint, et loadEnv lit en
  // plus les fichiers .env du projet.
  const env = loadEnv(mode, process.cwd(), '')
  const BACKEND = env.VITE_BACKEND_URL || 'http://localhost:8000'

  return {
  plugins: [react()],
  server: {
    allowedHosts: true,
    proxy: {
      '/api': {
        target: BACKEND,
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
  }
})
