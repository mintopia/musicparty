import {defineConfig} from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: [
            {
                find: '@/routes/inertia',
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-inertia.ts'),
            },
            {find: '@', replacement: path.resolve(import.meta.dirname, 'resources/js')},
        ],
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/tests/**/*.test.ts'],
    },
});
