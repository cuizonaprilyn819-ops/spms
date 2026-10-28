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
                spms: {
                    sky: '#0284C7',
                    navy: '#244C71',
                    green: '#15803D',
                    red: '#B42318',
                    amber: '#A16207',
                    surface: '#F5F7F9',
                    ink: '#172B3D',
                },
            },
        },
    },

    plugins: [forms],
};
