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
                sans: ['Public Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                blue: {
                    50: '#EDF3FC',
                    100: '#E1EBFA',
                    200: '#C3D5F1',
                    300: '#9AB9E3',
                    400: '#7098CF',
                    500: '#4E75B2',
                    600: '#34508E',
                    700: '#2B4276',
                    800: '#243965',
                    900: '#1B2D54',
                },
                spms: {
                    sky: '#0369A1',
                    navy: '#34508E',
                    'navy-hover': '#283E72',
                    tint: '#EAF1FB',
                    line: '#DCE5F0',
                    teal: '#22736D',
                    green: '#15803D',
                    red: '#B42318',
                    amber: '#A16207',
                    surface: '#F3F7FC',
                    ink: '#1E2A4A',
                },
            },
        },
    },

    plugins: [forms],
};
