import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  // « coverage » est produit par vitest --coverage : du code instrumenté, dont
  // les directives eslint-disable font du bruit dans le rapport de lint.
  globalIgnores(['dist', 'coverage']),
  {
    files: ['**/*.{js,jsx}'],
    extends: [
      js.configs.recommended,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
  },
  {
    // Seul tailwind.config.js est resté en CommonJS (module.exports, require),
    // alors que le paquet est en "type": "module" — le chargeur de Tailwind
    // l'accepte. Les autres fichiers de configuration sont en ESM et doivent
    // garder sourceType: module, d'où le ciblage nominatif.
    files: ['tailwind.config.js'],
    languageOptions: {
      globals: globals.node,
      sourceType: 'commonjs',
    },
  },
  {
    // vite.config.js s'execute sous Node, au moment du build, et y lit
    // process.cwd() pour resoudre les fichiers .env. Il reste en ESM, d'ou un
    // bloc distinct de celui de tailwind.config.js.
    files: ['vite.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
  {
    // Les fichiers de test s'exécutent sous Vitest, qui expose describe, it,
    // expect, beforeEach et afterEach comme globales. Sans cette déclaration,
    // ESLint les signalait comme variables non définies — du bruit qui masquait
    // de vraies erreurs du même type dans le code applicatif.
    files: ['src/test/**/*.{js,jsx}'],
    languageOptions: {
      globals: { ...globals.browser, ...globals.node, ...globals.vitest },
    },
  },
])
