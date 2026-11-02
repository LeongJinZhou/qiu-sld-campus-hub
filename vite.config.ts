import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // The React frontend (resources/react) is built alongside the Vue one until every page is converted.
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/react/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Poppins', {
                    weights: [400, 500, 600, 700],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        react({ include: /resources\/react\/.*\.tsx?$/ }),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@react': '/resources/react', '@': '/resources/js' },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
