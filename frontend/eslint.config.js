import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';

export default tseslint.config(
  { ignores: ['dist'] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': [
        'warn',
        { allowConstantExport: true },
      ],

      // Стилистика проекта: явные типы аргументов уже задаются через
      // props-интерфейсы компонентов, поэтому возвращаемый тип функций
      // выводим автоматически, а не требуем аннотировать его руками.
      '@typescript-eslint/explicit-function-return-type': 'off',

      // unknown в catch (err: unknown) — уже принятый в проекте стиль
      // (см. api/client.ts, App.tsx) вместо `any`, поэтому просто следим,
      // чтобы `any` не просочился где-то ещё.
      '@typescript-eslint/no-explicit-any': 'error',

      // Неиспользуемые параметры разрешаем, если они начинаются с "_"
      // (см. App.tsx: handleCreated(_order: Order) — параметр пока не
      // нужен, но сигнатура отражает реальные данные, которые приходят).
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
    },
  },
);
