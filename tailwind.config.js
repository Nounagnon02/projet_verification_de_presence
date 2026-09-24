import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Corps de texte : très lisible, pensée pour les lecteurs peu à l'aise avec le numérique.
                // La famille passe par une variable CSS parce qu'elle dépend de la langue :
                // Atkinson Hyperlegible ne contient ni ẹ (U+1EB9) ni ọ (U+1ECD) ni les
                // marques de ton U+0300/U+0301, indispensables en yoruba et en fon.
                // Voir resources/views/components/fonts.blade.php.
                sans: ['var(--font-sans)', ...defaultTheme.fontFamily.sans],
                // Titres et intitulés de marque : chaleureux, digne, jamais générique.
                display: ['Spectral', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            screens: {
                'xs': '475px',
                'sm': '640px',
                'md': '768px',
                'lg': '1024px',
                'xl': '1280px',
                '2xl': '1536px',
                'tablet': '768px',
                'laptop': '1024px',
                'desktop': '1280px',
            },
            animation: {
                'fade-in-up': 'fadeInUp 0.5s ease-out',
                'slide-in-right': 'slideInRight 0.3s ease-out',
                'pulse-soft': 'pulse-soft 2s infinite',
                'bounce-subtle': 'bounce-subtle 0.3s ease-in-out',
            },
            colors: {
                // Palette validée (direction "chaleureuse, sobre, très lisible") — un seul thème, pas de mode sombre.
                paper: '#FAF5EC',      // fond de page
                paper2: '#F3ECDF',     // fond secondaire (sidebar, blocs alternés)
                card: '#FFFFFF',       // cartes, formulaires
                line: '#E3D8C6',       // bordures discrètes
                'line-strong': '#C7BBAA', // bordures de boutons secondaires
                ink: '#2A241F',        // texte principal
                'ink-soft': '#6B5F52', // texte secondaire
                'ink-faint': '#9C8F80',// texte tertiaire / métadonnées
                accent: {
                    DEFAULT: '#A6472A',
                    hover: '#8A3820',
                    tint: '#F3DDD2',
                    focus: '#E7B9A6',
                },
                confirm: {
                    DEFAULT: '#3F6B52',
                    tint: '#E1EBE4',
                },
            },
        },
    },

    plugins: [forms],
};
