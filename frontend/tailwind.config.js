/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: ['class'],
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        'on-primary-fixed-variant': '#344478',
        'primary-fixed-dim': '#b5c4ff',
        'tertiary-fixed-dim': '#ffb3ae',
        'surface-container-highest': '#e0e3e6',
        'surface': '#f7f9fd',
        'background': '#f7f9fd',
        'surface-container-high': '#e6e8ec',
        'on-primary': '#ffffff',
        'error-container': '#ffdad6',
        'secondary-container': '#75f8b3',
        'on-secondary-container': '#007147',
        'tertiary': '#3d0004',
        'on-error-container': '#93000a',
        'surface-variant': '#e0e3e6',
        'on-surface-variant': '#45464f',
        'surface-bright': '#f7f9fd',
        'surface-container': '#eceef2',
        'on-surface': '#191c1f',
        'secondary-fixed-dim': '#59de9b',
        'secondary-fixed': '#78fbb6',
        'on-tertiary-container': '#ff5f5c',
        'secondary': '#006d43',
        'on-primary-container': '#8494cd',
        'tertiary-container': '#64000a',
        'inverse-on-surface': '#eff1f5',
        'surface-container-lowest': '#ffffff',
        'on-tertiary': '#ffffff',
        'primary-container': '#1a2b5e',
        'on-secondary': '#ffffff',
        'surface-dim': '#d8dade',
        'on-tertiary-fixed': '#410004',
        'tertiary-fixed': '#ffdad7',
        'outline-variant': '#c5c6d1',
        'inverse-surface': '#2d3134',
        'on-primary-fixed': '#03174b',
        'surface-container-low': '#f2f4f8',
        'on-secondary-fixed': '#002111',
        'on-background': '#191c1f',
        'primary': '#011549',
        'on-error': '#ffffff',
        'inverse-primary': '#b5c4ff',
        'on-secondary-fixed-variant': '#005232',
        'on-tertiary-fixed-variant': '#930014',
        'error': '#ba1a1a',
        'surface-tint': '#4c5c92',
        'primary-fixed': '#dce1ff',
        'outline': '#757680'
      },
      borderRadius: {
        'DEFAULT': '0.25rem',
        'lg': '0.5rem',
        'xl': '0.75rem',
        'xxl': '1.5rem',
        'full': '9999px'
      },
      fontFamily: {
        headline: ['Sora', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
        label: ['Inter', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace']
      },
      keyframes: {
        'pulse-slow': {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.5' }
        }
      }
    },
  },
  plugins: [require('@tailwindcss/container-queries')],
}
