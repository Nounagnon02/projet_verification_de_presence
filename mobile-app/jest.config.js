/**
 * Harnais de tests du client mobile (O11–O14 du plan de tests).
 *
 * Le preset jest-expo fournit le transformeur Babel, la resolution des
 * plateformes et les doubles de base des modules natifs. Sans lui, chaque
 * import d'un module expo-* echoue a la compilation.
 */
module.exports = {
  preset: 'jest-expo',

  // Les paquets de l'ecosysteme React Native sont publies en ES modules non
  // transpiles : Jest doit les traverser au lieu de les ignorer comme le reste
  // de node_modules.
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?)'
      + '|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*'
      + '|react-navigation|@react-navigation/.*'
      + '|@unimodules/.*|unimodules'
      + '|sentry-expo|native-base'
      + '|react-native-svg|react-native-wifi-reborn'
      + '|nativewind|react-native-css-interop'
      + '|lucide-react-native'
      + '|react-native-toast-message'
      + ')',
  ],

  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],

  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
    // lucide-react-native expose une condition d'export « react-native » qui
    // pointe vers un .mjs. jest-expo resout les conditions d'export dans cet
    // ordre et choisit ce fichier avant le CJS — Jest ne le transforme pas
    // (aucune regle « transform » ne cible .mjs), et « export » brut fait
    // planter le test a l'import. On force la resolution vers le CJS.
    '^lucide-react-native$': '<rootDir>/node_modules/lucide-react-native/dist/cjs/lucide-react-native.js',
  },

  testMatch: [
    '<rootDir>/src/**/__tests__/**/*.(test|spec).(ts|tsx|js|jsx)',
    '<rootDir>/src/**/*.(test|spec).(ts|tsx|js|jsx)',
    '<rootDir>/app/**/*.(test|spec).(ts|tsx|js|jsx)',
  ],

  collectCoverageFrom: [
    'src/**/*.{ts,tsx}',
    'app/**/*.{ts,tsx}',
    '!**/*.d.ts',
    '!**/__tests__/**',
  ],

  // Cliquet, comme cote web. Premiere mesure (2026-08-22) : 6,95 % de lignes.
  // Relevee au 2026-09-18 a 39,5 % (lignes), avec l'ajout des tests de
  // connexion et de scan authentifie. Encore a couvrir : useLocation, useWifi,
  // useFingerprint, les composants scanner/ui, et les ecrans hors connexion.
  coverageThreshold: {
    global: { lines: 42, functions: 30, branches: 28, statements: 39 },
  },
};
