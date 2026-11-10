import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './src/**/*.js',
        './src/**/*.vue',
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    green: '#2E4433',
                    'green-deep': '#223829',
                    cream: '#F7F1E4',
                    amber: '#E3A33C',
                    'amber-deep': '#D9972E',
                    ink: '#1E3A2A',
                    muted: '#7C8A80',
                    field: '#F6EFDF',
                    border: '#ECE3D2',
                    terracotta: '#A8573F',
                    sage: '#789676',
                    sand: '#7A7248',
                },
            },
        },
    },
    plugins: [],
};
