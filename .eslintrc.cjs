module.exports = {
    root: true,
    env: {
        browser: true,
        es2022: true,
    },
    extends: ['eslint:recommended'],
    parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        ecmaFeatures: { jsx: true },
    },
    rules: {
        // ESLint core does not mark JSX component identifiers as used. Keep the
        // dependency set small while still catching unused lower-case values.
        'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^[A-Z]' }],
        'no-empty': ['error', { allowEmptyCatch: true }],
    },
};
