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
  // Le harnais vient d'etre monte et ne couvre pour l'instant que l'empreinte
  // d'appareil et le stockage du jeton. Les seuils sont cales juste dessous et
  // doivent monter a chaque lot ajoute — hooks (useScan, useLocation, useWifi),
  // client API, contexte d'authentification, puis ecrans.
  coverageThreshold: {
    global: { lines: 6, functions: 9, branches: 3, statements: 6 },
  },
};
