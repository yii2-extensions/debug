import assert from "node:assert/strict";
import test from "node:test";

import {
  addThemeToDebugUrl,
  normalizeTheme,
  readStoredTheme,
  writeTheme,
} from "../src/core/theme.js";

function installBrowserGlobals() {
  const values = new Map();

  globalThis.window = {
    location: new URL("https://example.test/debug/default/view?tag=1"),
    localStorage: {
      getItem(key) {
        return values.get(key) ?? null;
      },
      setItem(key, value) {
        values.set(key, value);
      },
    },
  };
  globalThis.localStorage = window.localStorage;
  globalThis.document = { cookie: "" };
}

test("normalizeTheme accepts explicit aliases without matching modifier classes", () => {
  assert.equal(normalizeTheme("night"), "dark");
  assert.equal(normalizeTheme("LIGHT"), "light");
  assert.equal(normalizeTheme("dark:bg-slate-900"), null);
  assert.equal(normalizeTheme("light dark"), null);
});

test("addThemeToDebugUrl updates same-origin debug routes only", () => {
  installBrowserGlobals();

  assert.equal(
    addThemeToDebugUrl("/debug/default/view?tag=2", "dark"),
    "https://example.test/debug/default/view?tag=2&yii_debug_theme=dark",
  );
  assert.equal(addThemeToDebugUrl("/site/index", "dark"), "/site/index");
  assert.equal(
    addThemeToDebugUrl("https://external.test/debug/default/view", "dark"),
    "https://external.test/debug/default/view",
  );
});

test("writeTheme persists the normalized theme", () => {
  installBrowserGlobals();

  writeTheme("night");

  assert.equal(readStoredTheme(), "dark");
  assert.match(document.cookie, /^yii-debug-toolbar-theme=dark;/);
});
