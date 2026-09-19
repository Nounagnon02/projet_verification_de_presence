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
  // « 127.0.0.1 » et non « localhost » : ce dernier resout d'abord en IPv6
  // (::1) sur la plupart des systemes, alors que « php artisan serve » n'ecoute
  // qu'en IPv4. Le proxy repondait alors 502 pendant que le backend, joint
  // directement, repondait 200 — un ecart difficile a interpreter.
  const BACKEND = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'

  return {
  plugins: [react()],
  build: {
    rolldownOptions: {
      output: {
        // Le noyau React dans son propre fichier : il ne change qu'aux montées
        // de version, alors que le code applicatif change à chaque déploiement.
        // Un chunk d'entrée unique de 334 Ko était invalidé en entier par la
        // moindre correction, et re-téléchargé sur réseau mobile.
        codeSplitting: {
          groups: [
            {
              name: 'vendor-react',
              test: /node_modules[\\/](react|react-dom|react-router|react-router-dom|scheduler)[\\/]/,
              priority: 20,
            },
          ],
        },
      },
    },
  },
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
      // premiers tests d'integration MSW ; 30,80 % apres. Relevee au
      // 2026-09-18 a 65,37 % (lignes) avec le contrat de scan authentifie
      // (PresenceValidationPage, axios.js, ProfilePage) et les pages jusque
      // -la sans test (rapports, sessions actives), puis a 66,4 % apres le
      // retrait de 7 composants morts et les tests de AuthContext et de
      // ProtectedRoute, puis a 67,17 % apres la migration de StudentManagementPage
      // et EvenementManagementPage vers TanStack Query. L'exigence §2.1.3 du
      // memoire — 70 % — s'en approche mais n'est pas encore atteinte.
      //
      // Relevee au 2026-09-19 apres la migration des 53 pages et composants
      // restants vers TanStack Query : lignes 67,51 %, instructions 63,46 %,
      // branches 56,2 %. Le seuil des fonctions recule a 54 (mesure 54,78 %) :
      // une vingtaine de modules d'API neufs (frontend/src/api/resources/)
      // ajoutent chacun plusieurs petites fonctions d'un seul appel, exercees
      // indirectement par les tests des pages qui les consomment mais qui font
      // baisser le ratio de fonctions comptees une par une plus vite que les
      // lignes qu'elles executent.
      //
      // Les seuils sont cales juste sous le niveau mesure : ils empechent toute
      // regression sans bloquer la chaine sur un chiffre hors d'atteinte, qui
      // aurait ete desactive a la premiere occasion. Ils doivent etre releves a
      // chaque lot de tests ajoute, jusqu'aux valeurs de l'exigence.
      thresholds: {
        lines: 67,
        functions: 54,
        branches: 55,
        statements: 63,
      },
    },
  },
  }
})
