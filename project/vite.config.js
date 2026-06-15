import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',     // Открываем доступ снаружи Docker
        port: 5173,          // Фиксируем порт
        strictPort: true,    // Запрещаем прыгать на 5174
        hmr: {
            host: 'localhost', // Браузер на ПК будет слать сокеты сюда
        },
    },
});
