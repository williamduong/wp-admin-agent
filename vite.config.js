import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { extractBundleCss } from './scripts/extract-bundle-css.mjs';

export default defineConfig(({ command }) => ({
    plugins: [
        react(),
        { name: 'wradmin-extract-css', writeBundle: extractBundleCss },
    ],

    // WordPress supplies React and ReactDOM through the wp-element script.
    resolve: command === 'build' ? {
        alias: {
            'react/jsx-runtime': fileURLToPath(new URL('./src/wp-jsx-runtime.js', import.meta.url)),
        },
    } : {},

    build: {
        outDir:   'assets',
        emptyOutDir: true,
        cssCodeSplit: true,
        rollupOptions: {
            input: 'src/index.jsx',
            external: ['react', 'react-dom/client'],
            output: {
                format: 'iife',
                globals: {
                    react: 'wp.element',
                    'react-dom/client': 'wp.element',
                },
                entryFileNames: 'js/admin-agent.js',
                assetFileNames: 'css/admin-agent.css',
                // Prevent hash suffix on filenames
                chunkFileNames: 'js/[name].js',
            },
        },
    },

    test: {
        environment: 'jsdom',
        setupFiles:  ['tests/js/setup.js'],
        coverage: {
            provider:   'v8',
            reporter:   ['text', 'lcov'],
            thresholds: { lines: 60 },
        },
    },
}));
