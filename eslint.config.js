import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    {
        ignores: [
            'vendor/**',
            'docker/**',
            'node_modules/**',
            'public/**',
            'storage/**',
            'resources/js/routes/**',
            'resources/js/actions/**',
            'resources/js/wayfinder/**',
        ],
    },
    {
        files: [
            'resources/js/inertia.ts',
            'resources/js/types.ts',
            'resources/js/pages/**/*.{ts,vue}',
            'resources/js/tests/**/*.ts',
        ],
        extends: [
            js.configs.recommended,
            ...tseslint.configs.recommended,
            ...pluginVue.configs['flat/recommended'],
            prettier,
        ],
        languageOptions: {
            globals: globals.browser,
            parserOptions: {parser: tseslint.parser},
        },
    },
    {
        files: ['resources/js/pages/**/*.vue'],
        rules: {'vue/multi-word-component-names': 'off'},
    },
);
