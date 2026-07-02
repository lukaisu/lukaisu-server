import js from "@eslint/js";
import globals from "globals";
import tseslint from "typescript-eslint";
import markdown from "@eslint/markdown";
import css from "@eslint/css";
import { defineConfig } from "eslint/config";

export default defineConfig([
  // Global ignores - these directories are completely excluded
  {
    ignores: [
      "node_modules/**",
      "vendor/**",
      "dist/**",
      "assets/**",
      "coverage/**",
      "coverage-report/**",
      "docs/generated/**",
    ],
  },

  // Server-side TS source (server-src/: the CSS-only build entry + the
  // service worker). No Svelte, no app modules — those moved to the sibling
  // `lukaisu` app repo (Phase M).
  {
    files: ["server-src/**/*.{js,mjs,cjs,ts,mts,cts}"],
    plugins: { js },
    extends: ["js/recommended"],
    languageOptions: {
      globals: globals.browser,
    },
  },
  {
    files: ["server-src/**/*.{ts,mts,cts}"],
    extends: [tseslint.configs.recommended],
  },

  // Test files (the tests/api.test.ts live-server smoke test, Cypress e2e)
  {
    files: ["tests/**/*.{js,ts}", "cypress/**/*.{js,ts}"],
    plugins: { js },
    extends: ["js/recommended"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
  },

  // TypeScript test files
  {
    files: ["tests/**/*.ts", "cypress/**/*.ts"],
    extends: [tseslint.configs.recommended],
    rules: {
      // Allow namespace declarations for Cypress type augmentation
      "@typescript-eslint/no-namespace": "off",
      // Allow explicit any in tests (common for mocking)
      "@typescript-eslint/no-explicit-any": "off",
    },
  },

  // Config files (vite.config.ts, eslint.config.ts, etc.)
  {
    files: ["*.config.{js,ts}", "scripts/**/*.js"],
    plugins: { js },
    extends: ["js/recommended"],
    languageOptions: {
      globals: globals.node,
    },
  },

  // TypeScript config files
  {
    files: ["*.config.ts"],
    extends: [tseslint.configs.recommended],
  },

  // Markdown files
  {
    files: ["*.md", "docs/**/*.md"],
    plugins: { markdown },
    language: "markdown/commonmark",
    extends: ["markdown/recommended"],
    rules: {
      // Disable rules that produce false positives for GitHub-flavored markdown
      // (GitHub alerts like [!NOTE], [!IMPORTANT], checkbox syntax, etc.)
      "markdown/no-missing-label-refs": "off",
      // Allow emphasis markers with spaces in specific cases
      "markdown/no-space-in-emphasis": "off",
    },
  },

  // CSS files (the frozen base-theme snapshot the last 2 server pages use)
  {
    files: ["assets/css/**/*.css"],
    plugins: { css },
    language: "css/css",
    extends: ["css/recommended"],
    rules: {
      // Allow !important - this codebase legitimately uses !important for
      // overriding Bulma framework defaults and state/utility classes.
      // Refactoring to avoid !important would require major CSS architecture changes.
      "css/no-important": "off",
      // The flagged properties (user-select, resize, accent-color) are well-supported
      // in all modern browsers since 2021-2022. Disabling to avoid false positives.
      "css/use-baseline": "off",
      // Theme CSS references custom properties defined in styles.css's :root
      // block. The linter can't resolve cross-file variables.
      "css/no-invalid-properties": ["error", { allowUnknownVariables: true }],
    },
  },
]);
