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
            {
                find: /^@\/routes\/api\/v1\/parties\/upcomingsongs$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-upcomingsongs.ts'),
            },
            {
                find: '@/routes/api/v1/parties/upcomingsongs/vote',
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-vote.ts'),
            },
            {
                find: /^@\/routes\/parties$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-parties.ts'),
            },
            {find: '@', replacement: path.resolve(import.meta.dirname, 'resources/js')},
        ],
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/tests/**/*.test.ts'],
    },
});
