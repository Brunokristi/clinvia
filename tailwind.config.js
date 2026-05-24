import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                accent: 'var(--color-accent)',
                soft: 'var(--color-soft)',
                white: 'var(--color-white)',
                dark: 'var(--color-dark)',
            },

            fontFamily: {
                normal: ['var(--font-normal)'],
                heading: ['var(--font-heading)'],
            },

            fontSize: {
                normal: [
                    'var(--text-normal-size)',
                    {
                        lineHeight: 'var(--text-normal-line-height)',
                    },
                ],
                heading: [
                    'var(--text-heading-size)',
                    {
                        lineHeight: 'var(--text-heading-line-height)',
                    },
                ],
            },
        },
    },

    plugins: [forms],
};
