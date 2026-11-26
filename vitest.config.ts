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
                find: '@/routes/api/v1/parties/playedsongs/rate',
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-rate.ts'),
            },
            {
                find: /^@\/routes\/parties$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-parties.ts'),
            },
            {
                find: /^@\/routes\/api\/v1$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-ping.ts'),
            },
            {
                find: /^@\/routes\/parties\/songs$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-songs.ts'),
            },
            {
                find: /^@\/routes\/api\/v1\/parties$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-api-parties.ts'),
            },
            {
                find: /^@\/routes\/api\/v1\/parties\/playback$/,
                replacement: path.resolve(import.meta.dirname, 'resources/js/tests/stubs/routes-api-playback.ts'),
            },
            {find: '@', replacement: path.resolve(import.meta.dirname, 'resources/js')},
        ],
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/tests/**/*.test.ts'],
    },
});
