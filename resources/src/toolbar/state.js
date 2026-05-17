/**
 * Module-level state shared across the toolbar sub-modules.
 *
 * The original IIFE held all of these in a single closure; once the file was
 * split into ES modules, the same values live here and every other module
 * imports them. Array bindings (`requestStack`, `toolbars`) are still mutated
 * in place — `push`/`splice` carry across modules, the bindings themselves
 * never change.
 *
 * `originalXhrOpen` / `originalFetch` are captured at module load time, BEFORE
 * any other code installs replacement implementations, so the AJAX tracker can
 * always delegate to the unhooked browser primitives.
 */

export var tagName = "yii-debug-toolbar";
export var storageKey = "yii-debug-toolbar-expanded";
export var themeParam = "yii_debug_theme";
export var themeStorageKey = "yii-debug-toolbar-theme";
export var requestStackLimit = 100;
export var requestStack = [];
export var toolbars = [];

export var themeAttributeFilter = [
  "class",
  "data-theme",
  "data-bs-theme",
  "data-yii-debug-theme",
  "data-color-mode",
  "data-mode",
  "data-theme-mode",
];

export var originalXhrOpen = XMLHttpRequest.prototype.open;
export var originalFetch = window.fetch;

export function escapeHtml(value) {
  return String(value === null || typeof value === "undefined" ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export function parseJsonAttribute(element, attribute, fallback) {
  var value = element.getAttribute(attribute);

  if (!value) {
    return fallback;
  }

  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

export function absoluteUrl(url) {
  try {
    return new URL(url, window.location.href);
  } catch {
    return null;
  }
}

export function sameUrl(left, right) {
  var leftUrl = absoluteUrl(left);
  var rightUrl = absoluteUrl(right);

  return (
    leftUrl !== null && rightUrl !== null && leftUrl.href === rightUrl.href
  );
}

export function getPrimaryToolbar() {
  return toolbars.length ? toolbars[0] : document.querySelector(tagName);
}

export function closest(element, selector) {
  if (element && element.nodeType !== 1) {
    element = element.parentElement;
  }

  while (element && element.nodeType === 1) {
    if (element.matches(selector)) {
      return element;
    }
    element = element.parentElement;
  }

  return null;
}
