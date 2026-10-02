import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                fontsource('Inter', {
                    weights: [400, 500, 600, 700],
                    variable: '--font-inter',
                }),
                fontsource('Barlow Condensed', {
                    weights: [600, 700],
                    variable: '--font-barlow-condensed',
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
