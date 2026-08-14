import js from '@eslint/js';
import configPrettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'bootstrap/ssr/**', 'storage/**'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            globals: { ...globals.browser, ...globals.es2025, route: 'readonly' },
            parserOptions: { ecmaFeatures: { jsx: true } },
        },
        plugins: { react, 'react-hooks': reactHooks },
        settings: { react: { version: 'detect' } },
        rules: {
            ...react.configs.recommended.rules,
            ...reactHooks.configs.recommended.rules,
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            '@typescript-eslint/consistent-type-imports': ['error', { prefer: 'type-imports', fixStyle: 'inline-type-imports' }],
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            eqeqeq: ['error', 'smart'],
        },
    },
    {
        files: ['**/*.test.{ts,tsx}', 'resources/js/tests/**/*.{ts,tsx}'],
        languageOptions: { globals: { ...globals.node, ...globals.vitest } },
    },
    {
        files: ['vite.config.ts', 'eslint.config.js'],
        languageOptions: { globals: globals.node },
    },
    configPrettier,
);
