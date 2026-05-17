import { absoluteUrl, themeParam, themeStorageKey } from "./state.js";

/**
 * Theme detection and propagation helpers.
 *
 * Theme resolution order (most authoritative first):
 *   1. The toolbar's own `data-yii-debug-theme` attribute.
 *   2. Host DOM signals on <html>/<body> (data-theme, classes, …).
 *   3. Toolbar's localStorage key (only when we own the toggle).
 *   4. Common host localStorage keys (theme, color-theme, vite-ui-theme, …).
 *   5. Computed `color-scheme`.
 *   6. `prefers-color-scheme: dark` media query.
 */

export function normalizeTheme(value) {
  var theme;
  var tokens;
  var darkAliases = ["dark", "night", "black"];
  var lightAliases = ["light", "day", "white"];
  var hasDark;
  var hasLight;
  var i;

  if (!value) {
    return null;
  }

  theme = String(value).toLowerCase().trim();

  if (theme === "") {
    return null;
  }

  /* Exact match (typical for explicit theme values like data-theme="dark"). */
  if (darkAliases.indexOf(theme) !== -1) {
    return "dark";
  }

  if (lightAliases.indexOf(theme) !== -1) {
    return "light";
  }

  /**
   * Token-based match — used for class lists. We must NOT use a substring
   * search here, because frameworks like Tailwind emit modifier classes such
   * as `dark:bg-gray-900` even in light mode; the modifier prefix means the
   * class only applies WHEN the document is in dark mode, not that it IS
   * dark. We only treat `dark` / `light` as a signal when they appear as a
   * standalone class token.
   */
  tokens = theme.split(/\s+/);
  hasDark = false;
  hasLight = false;

  for (i = 0; i < tokens.length; i++) {
    if (darkAliases.indexOf(tokens[i]) !== -1) {
      hasDark = true;
    } else if (lightAliases.indexOf(tokens[i]) !== -1) {
      hasLight = true;
    }
  }

  if (hasDark && !hasLight) {
    return "dark";
  }

  if (hasLight && !hasDark) {
    return "light";
  }

  return null;
}

export function getElementTheme(element) {
  var attributes = [
    "data-yii-debug-theme",
    "data-bs-theme",
    "data-theme",
    "data-color-mode",
    "data-mode",
    "data-theme-mode",
  ];
  var className;
  var i;
  var theme;

  if (!element) {
    return null;
  }

  for (i = 0; i < attributes.length; i++) {
    theme = normalizeTheme(element.getAttribute(attributes[i]));
    if (theme) {
      return theme;
    }
  }

  className = typeof element.className === "string" ? element.className : "";
  if (!className) {
    return null;
  }

  return normalizeTheme(className.split(/\s+/).join(" "));
}

export function getStorageTheme() {
  var keys = [
    "theme",
    "color-theme",
    "colorScheme",
    "color-scheme",
    "data-bs-theme",
    "bs-theme",
    "ui-theme",
    "preferred-theme",
    "vite-ui-theme",
    "vueuse-color-scheme",
  ];
  var i;
  var theme;

  if (!window.localStorage) {
    return null;
  }

  for (i = 0; i < keys.length; i++) {
    theme = normalizeTheme(localStorage.getItem(keys[i]));
    if (theme) {
      return theme;
    }
  }

  return null;
}

export function getComputedTheme() {
  var colorScheme;

  if (!window.getComputedStyle) {
    return null;
  }

  colorScheme =
    getComputedStyle(document.documentElement).colorScheme ||
    (document.body ? getComputedStyle(document.body).colorScheme : "");

  return normalizeTheme(colorScheme);
}

/**
 * Best-effort heuristic that decides whether the host application already
 * exposes its own theme switcher. If it does we stay passive and follow
 * whatever the host sets; if it does not we surface our own toggle inside
 * the toolbar so the dev can flip the panel UI without leaving the page.
 *
 * We only count a *visible* button-like element with a theme-related label
 * as a positive signal. localStorage keys like `theme` are intentionally
 * NOT used here — they can leak across origins / older visits and would
 * give false positives that hide our toggle on apps that don't actually
 * ship one.
 */
export function hostHasThemeControl() {
  var labelPattern = /\b(theme|mode|dark|light|night|day)\b/i;
  var nodes = document.querySelectorAll(
    'button, a, [role="switch"], [role="button"], [data-theme-toggle], [data-bs-theme-toggle]',
  );
  var i;
  var node;
  var label;

  for (i = 0; i < nodes.length; i++) {
    node = nodes[i];
    label =
      (node.getAttribute("aria-label") || "") +
      " " +
      (node.getAttribute("title") || "") +
      " " +
      (node.dataset && node.dataset.themeToggle ? "theme-toggle" : "");

    if (!labelPattern.test(label)) {
      continue;
    }

    /**
     * Treat the candidate as real only if it's actually rendered. This
     * skips off-DOM templates and `display: none` panels that some apps
     * ship for non-active states.
     */
    if (node.offsetParent !== null || node.getClientRects().length > 0) {
      return true;
    }
  }

  return false;
}

/**
 * Persist the theme as a same-origin cookie so the backend (`primeThemeContext`)
 * serves panel pages with the matching theme even when the URL doesn't carry
 * the `?yii_debug_theme=` query — e.g. when the host writes `localStorage.theme`
 * and the toolbar follows. Mirror writes from every theme mutation point
 * (toolbar toggle, host observer, postMessage from inside the panel) so the
 * cookie is always the latest authority.
 */
export function writeThemeCookie(theme) {
  if (!theme) {
    return;
  }
  try {
    document.cookie =
      themeStorageKey +
      "=" +
      encodeURIComponent(theme) +
      ";path=/;max-age=31536000;SameSite=Lax";
  } catch (_e) {
    /* Cookie writes can be blocked (CSP, sandboxed iframe) — ignore silently. */
  }
}

export function addThemeToUrl(url, theme) {
  var parsed = absoluteUrl(url);
  var routeParam;

  if (!parsed || !theme) {
    return url;
  }

  if (parsed.origin && parsed.origin !== window.location.origin) {
    return url;
  }

  /**
   * Only stamp debug routes — covers both URL conventions:
   *   - Pretty URLs:  `/debug/default/...`            → pathname.
   *   - Default Yii:  `/index.php?r=debug%2Fdefault…` → `r` query param.
   */
  routeParam = parsed.searchParams.get("r") || "";
  if (
    parsed.pathname.indexOf("/debug/") === -1 &&
    routeParam.indexOf("debug/") !== 0 &&
    routeParam.indexOf("debug%2F") !== 0
  ) {
    return url;
  }

  parsed.searchParams.set(themeParam, theme);

  return parsed.href;
}
