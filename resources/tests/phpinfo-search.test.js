import assert from "node:assert/strict";
import test from "node:test";

import {
  formatPhpInfoFilteredCount,
  formatPhpInfoSearchStatus,
  normalizePhpInfoQuery,
  resolvePhpInfoTocGroupState,
} from "../src/panels/phpinfo-search.js";

test("formatPhpInfoFilteredCount keeps the original total visible", () => {
  assert.equal(formatPhpInfoFilteredCount(1, "142 rows"), "1 of 142 rows");
  assert.equal(
    formatPhpInfoFilteredCount(3, "99 directives"),
    "3 of 99 directives",
  );
});

test("normalizePhpInfoQuery trims and normalizes case", () => {
  assert.equal(normalizePhpInfoQuery("  Memory_Limit  "), "memory_limit");
  assert.equal(normalizePhpInfoQuery(null), "");
});

test("formatPhpInfoSearchStatus describes matching settings", () => {
  assert.equal(formatPhpInfoSearchStatus(1, 1, 0), "1 match in 1 module");
  assert.equal(formatPhpInfoSearchStatus(4, 2, 0), "4 matches in 2 modules");
});

test("formatPhpInfoSearchStatus keeps module-name matches distinct", () => {
  assert.equal(formatPhpInfoSearchStatus(0, 0, 1), "1 module");
  assert.equal(
    formatPhpInfoSearchStatus(2, 1, 1),
    "1 module · 2 matches in 1 module",
  );
});

test("resolvePhpInfoTocGroupState opens only the active module group", () => {
  assert.deepEqual(
    resolvePhpInfoTocGroupState(
      ["phpinfo-core", "phpinfo-date"],
      "phpinfo-date",
      null,
    ),
    { hidden: false, open: true },
  );
  assert.deepEqual(
    resolvePhpInfoTocGroupState(
      ["phpinfo-pdo", "phpinfo-pdo-mysql"],
      "phpinfo-date",
      null,
    ),
    { hidden: false, open: false },
  );
});

test("resolvePhpInfoTocGroupState exposes only groups with search results", () => {
  var visibleIds = { "phpinfo-pdo": true };

  assert.deepEqual(
    resolvePhpInfoTocGroupState(
      ["phpinfo-pdo", "phpinfo-pdo-mysql"],
      "",
      visibleIds,
    ),
    { hidden: false, open: true },
  );
  assert.deepEqual(
    resolvePhpInfoTocGroupState(
      ["phpinfo-core", "phpinfo-date"],
      "",
      visibleIds,
    ),
    { hidden: true, open: false },
  );
});
