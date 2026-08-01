export const THEME_PARAM = "yii_debug_theme";
export const THEME_STORAGE_KEY = "yii-debug-toolbar-theme";

export function normalizeTheme(value) {
  if (!value) {
    return null;
  }

  const aliases = String(value).toLowerCase().trim().split(/\s+/);
  const hasDark = aliases.some((alias) =>
    ["dark", "night", "black"].includes(alias),
  );
  const hasLight = aliases.some((alias) =>
    ["light", "day", "white"].includes(alias),
  );

  if (hasDark === hasLight) {
    return null;
  }

  return hasDark ? "dark" : "light";
}

export function readStoredTheme(key = THEME_STORAGE_KEY) {
  try {
    return window.localStorage
      ? normalizeTheme(localStorage.getItem(key))
      : null;
  } catch {
    return null;
  }
}

export function readThemeCookie() {
  const prefix = `${THEME_STORAGE_KEY}=`;
  const cookie = (document.cookie || "")
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  if (!cookie) {
    return null;
  }

  try {
    return normalizeTheme(decodeURIComponent(cookie.slice(prefix.length)));
  } catch {
    return null;
  }
}

export function writeTheme(theme) {
  const normalized = normalizeTheme(theme);

  if (!normalized) {
    return;
  }

  try {
    localStorage.setItem(THEME_STORAGE_KEY, normalized);
  } catch {
    // Storage can be unavailable in private or sandboxed browsing contexts.
  }

  try {
    document.cookie = `${THEME_STORAGE_KEY}=${encodeURIComponent(normalized)};path=/;max-age=31536000;SameSite=Lax`;
  } catch {
    // Cookie writes can be blocked by the browser or iframe sandbox.
  }
}

export function addThemeToDebugUrl(url, theme) {
  const normalized = normalizeTheme(theme);
  let parsed;

  if (!normalized) {
    return url;
  }

  try {
    parsed = new URL(url, window.location.href);
  } catch {
    return url;
  }

  if (parsed.origin !== window.location.origin) {
    return url;
  }

  const route = parsed.searchParams.get("r") || "";

  if (
    !parsed.pathname.includes("/debug/") &&
    !route.startsWith("debug/") &&
    !route.startsWith("debug%2F")
  ) {
    return url;
  }

  parsed.searchParams.set(THEME_PARAM, normalized);

  return parsed.href;
}
