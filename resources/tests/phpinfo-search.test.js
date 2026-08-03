import assert from "node:assert/strict";
import test from "node:test";

import {
  formatPhpInfoSearchStatus,
  normalizePhpInfoQuery,
} from "../src/panels/phpinfo-search.js";

test("normalizePhpInfoQuery trims and normalizes case", () => {
  assert.equal(normalizePhpInfoQuery("  Memory_Limit  "), "memory_limit");
  assert.equal(normalizePhpInfoQuery(null), "");
});

test("formatPhpInfoSearchStatus describes directive results", () => {
  assert.equal(formatPhpInfoSearchStatus(1, 1, 0), "1 directive in 1 module");
  assert.equal(formatPhpInfoSearchStatus(4, 2, 0), "4 directives in 2 modules");
});

test("formatPhpInfoSearchStatus keeps module-name matches distinct", () => {
  assert.equal(formatPhpInfoSearchStatus(0, 0, 1), "1 module");
  assert.equal(
    formatPhpInfoSearchStatus(2, 1, 1),
    "1 module · 2 directives in 1 module",
  );
});
