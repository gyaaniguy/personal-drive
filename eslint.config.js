import js from '@eslint/js';
import react from 'eslint-plugin-react';
import globals from 'globals';
import prettier from 'eslint-plugin-prettier';
import prettierConfig from 'eslint-config-prettier';

export default [
    js.configs.recommended,
    {
        files: ['**/*.js', '**/*.jsx'],
        languageOptions: {
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                ecmaFeatures: {
                    jsx: true,
                },
            },
            globals: {
                ...globals.browser, // Includes all browser-related globals
                route: 'readonly', // Added manually to resolve 'route' is not defined
                axios: 'readonly', // Added manually to resolve 'axios' is not defined
            },
        },
        plugins: {react},
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/jsx-uses-vars': 'error',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        files: ['resources/js/**/*.js', 'resources/js/**/*.jsx'],
        plugins: { prettier },
        rules: {
            'prettier/prettier': 'error',
        },
    },
];