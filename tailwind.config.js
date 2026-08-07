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
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                spms: {
                    sky: '#5ABDEE',
                    navy: '#34508E',
                    green: '#28C76F',
                    red: '#EA5455',
                    amber: '#FF9F43',
                    surface: '#F2F4F7',
                    ink: '#142233',
                },
            },
        },
    },

    plugins: [forms],
};
