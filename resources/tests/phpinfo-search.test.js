import assert from "node:assert/strict";
import test from "node:test";

import {
  formatPhpInfoFilteredCount,
  formatPhpInfoSearchStatus,
  matchesPhpInfoCompactModule,
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

test("matchesPhpInfoCompactModule searches its title and summarized facts", () => {
  assert.equal(
    matchesPhpInfoCompactModule("xmlreader", "XMLReader enabled", "xmlreader"),
    true,
  );
  assert.equal(
    matchesPhpInfoCompactModule("fileinfo", "libmagic 5.46", "libmagic"),
    true,
  );
  assert.equal(
    matchesPhpInfoCompactModule("calendar", "Calendar support enabled", "pdo"),
    false,
  );
});

test("formatPhpInfoSearchStatus counts matching rows", () => {
  assert.equal(formatPhpInfoSearchStatus(0, 0, 0), "");
  assert.equal(formatPhpInfoSearchStatus(1, 0, 0), "1 matching row");
  assert.equal(formatPhpInfoSearchStatus(4, 0, 0), "4 matching rows");
});

test("formatPhpInfoSearchStatus names modules, extensions and rows apart", () => {
  assert.equal(formatPhpInfoSearchStatus(0, 1, 0), "1 module");
  assert.equal(formatPhpInfoSearchStatus(0, 0, 1), "1 extension");
  assert.equal(
    formatPhpInfoSearchStatus(1, 0, 1),
    "1 extension · 1 matching row",
  );
  assert.equal(
    formatPhpInfoSearchStatus(2, 2, 3),
    "2 modules · 3 extensions · 2 matching rows",
  );
});

test("resolvePhpInfoTocGroupState opens only the active module group", () => {
  assert.deepEqual(resolvePhpInfoTocGroupState([], "", null), {
    hidden: true,
    open: false,
  });
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
