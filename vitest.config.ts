import { defineConfig } from 'vitest/config';

/**
 * The only vitest surface left after Phase M (the frontend moved to the
 * sibling `lukaisu` repo, with its own vitest.config.ts) is
 * `tests/api.test.ts` — a live-server HTTP integration smoke test, not a unit
 * test. It's excluded from the default `test` run because it needs a real
 * running server (`fetch`-based); run it explicitly with
 * `npx vitest run tests/api.test.ts` against a live instance.
 */
export default defineConfig({
  test: {
    globals: true,
    include: ['tests/**/*.test.ts', 'tests/**/*.spec.ts'],
    exclude: ['node_modules', 'vendor', 'tests/api.test.ts'],
    // api.test.ts (the only file matching `include`) is deliberately excluded
    // above, so the default `test` run legitimately has zero applicable
    // files — that's success, not a failure to report.
    passWithNoTests: true,
    testTimeout: 10000,
    hookTimeout: 10000,
    environment: 'node',
  },
});
