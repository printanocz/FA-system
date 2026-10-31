import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor', 'node_modules', 'public', 'storage', 'bootstrap/cache'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    reactHooks.configs.flat.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: { globals: globals.browser },
    },
    prettier,
);
