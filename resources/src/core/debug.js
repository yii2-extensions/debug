import "../styles/main.css";
import "../styles/timeline.css";
import "./history-cursor.js";
import "../panels/db.js";
import "../panels/phpinfo-search.js";
import "../panels/userswitch.js";
import {
  addThemeToDebugUrl,
  normalizeTheme,
  readStoredTheme,
  readThemeCookie,
  THEME_PARAM,
  writeTheme,
} from "./theme.js";

(function () {
  "use strict";

  function getParentToolbarTheme() {
    var root;
    var host;

    try {
      if (!window.frameElement) {
        return null;
      }

      root = window.frameElement.getRootNode
        ? window.frameElement.getRootNode()
        : null;
      host = root && root.host ? root.host : null;

      return host ? normalizeTheme(host.getAttribute("data-theme")) : null;
    } catch {
      return null;
    }
  }

  function getUrlTheme() {
    try {
      return normalizeTheme(
        new URL(window.location.href).searchParams.get(THEME_PARAM),
      );
    } catch {
      return null;
    }
  }

  function applyTheme() {
    // Priority is "what the client most recently chose, regardless of stack":
    //   1. Parent toolbar theme (drawer iframe) — the live authority NOW.
    //   2. Cookie (last client write — survives reloads + backend staleness).
    //   3. localStorage fallback (cookie may be blocked in some sandboxes).
    //   4. Explicit `?yii_debug_theme=` query — deep links with no client
    //      state yet. The query is a snapshot frozen at link-render time,
    //      so it must NEVER outrank a later client choice: that is exactly
    //      how a stale `dark` link used to revert a fresh `light` pick.
    //   5. Server-rendered `data-yii-debug-theme` attribute.
    //   6. `prefers-color-scheme` media query as the very last resort.
    var theme =
      getParentToolbarTheme() ||
      readThemeCookie() ||
      readStoredTheme() ||
      getUrlTheme() ||
      normalizeTheme(
        document.documentElement.getAttribute("data-yii-debug-theme"),
      ) ||
      (window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light");

    document.documentElement.setAttribute("data-yii-debug-theme", theme);

    writeTheme(theme);

    return theme;
  }

  function preserveThemeInLinks(theme) {
    var links = document.querySelectorAll("a[href]");
    var forms = document.querySelectorAll("form[action]");
    var i;
    var input;

    for (i = 0; i < links.length; i++) {
      var href = links[i].getAttribute("href");
      if (href && href.charAt(0) !== "#" && href.indexOf("javascript:") !== 0) {
        links[i].setAttribute("href", addThemeToDebugUrl(href, theme));
      }
    }

    for (i = 0; i < forms.length; i++) {
      forms[i].setAttribute(
        "action",
        addThemeToDebugUrl(
          forms[i].getAttribute("action") || window.location.href,
          theme,
        ),
      );

      if ((forms[i].getAttribute("method") || "get").toLowerCase() !== "get") {
        continue;
      }

      input = forms[i].querySelector('input[name="' + THEME_PARAM + '"]');
      if (!input) {
        input = document.createElement("input");
        input.type = "hidden";
        input.name = THEME_PARAM;
        forms[i].appendChild(input);
      }
      input.value = theme;
    }
  }

  function bindThemeToggle() {
    var button = document.querySelector("[data-yii-debug-theme-toggle]");

    if (!button) {
      return;
    }

    var icon = button.querySelector(".yii-debug-brand-icon");

    button.addEventListener("click", function () {
      var current =
        normalizeTheme(
          document.documentElement.getAttribute("data-yii-debug-theme"),
        ) || "light";
      var next = current === "dark" ? "light" : "dark";

      document.documentElement.setAttribute("data-yii-debug-theme", next);
      button.setAttribute("data-current-theme", next);
      writeTheme(next);

      if (icon) {
        icon.innerHTML =
          next === "dark"
            ? button.getAttribute("data-icon-sun")
            : button.getAttribute("data-icon-moon");
      }

      if (window.parent && window.parent !== window) {
        window.parent.postMessage(
          { source: "yii-debug-toolbar", type: "theme", theme: next },
          window.location.origin,
        );
      }
    });
  }

  function closest(element, selector) {
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

  function findToggle(node, kind) {
    return closest(node, '[data-yii-debug-toggle="' + kind + '"]');
  }

  function hideDropdowns(except) {
    var wrappers = document.querySelectorAll(".yii-debug-dropdown.is-open");
    for (var i = 0; i < wrappers.length; i++) {
      var menu = wrappers[i].querySelector(".yii-debug-dropdown-menu");
      if (except && menu === except) {
        continue;
      }
      wrappers[i].classList.remove("is-open");
      var trigger = wrappers[i].querySelector(
        '[data-yii-debug-toggle="dropdown"]',
      );
      if (trigger) {
        trigger.setAttribute("aria-expanded", "false");
      }
    }
  }

  function activateTab(link) {
    var targetSelector = link.getAttribute("href");
    if (!targetSelector || targetSelector.charAt(0) !== "#") {
      return;
    }

    var target = document.querySelector(targetSelector);
    if (!target) {
      return;
    }

    var list = closest(link, ".yii-debug-tabs");
    var content = target.parentElement;
    var links = list
      ? list.querySelectorAll('[data-yii-debug-toggle="tab"]')
      : [];
    var panes = content ? content.children : [];
    var i;

    for (i = 0; i < links.length; i++) {
      links[i].classList.remove("is-active");
      links[i].setAttribute("aria-selected", "false");
      links[i].setAttribute("tabindex", "-1");
    }

    for (i = 0; i < panes.length; i++) {
      if (
        panes[i].classList &&
        panes[i].classList.contains("yii-debug-tab-panel")
      ) {
        panes[i].classList.remove("is-active");
        panes[i].hidden = true;
      }
    }

    link.classList.add("is-active");
    link.setAttribute("aria-selected", "true");
    link.setAttribute("tabindex", "0");
    target.classList.add("is-active");
    target.hidden = false;
  }

  preserveThemeInLinks(applyTheme());
  bindThemeToggle();

  document.addEventListener("click", function (event) {
    var tab = findToggle(event.target, "tab");
    var dropdown = findToggle(event.target, "dropdown");
    var collapse = findToggle(event.target, "collapse");
    var cellMore = findToggle(event.target, "cell-more");

    if (tab) {
      event.preventDefault();
      activateTab(tab);
      return;
    }

    if (cellMore) {
      var moreBox = closest(cellMore, ".yii-debug-cell-more");
      event.preventDefault();

      if (!moreBox) {
        return;
      }

      var moreOpen = moreBox.classList.toggle("is-open");
      cellMore.setAttribute("aria-expanded", moreOpen ? "true" : "false");
      cellMore.textContent = moreOpen ? "[-] Show less" : "[+] Show more";
      return;
    }

    if (collapse) {
      var targetSelector =
        collapse.getAttribute("data-target") || collapse.getAttribute("href");
      var target = targetSelector
        ? document.querySelector(targetSelector)
        : null;
      event.preventDefault();

      if (!target) {
        return;
      }

      var isShown = target.classList.contains("is-open");
      target.classList.toggle("is-open", !isShown);
      collapse.setAttribute("aria-expanded", isShown ? "false" : "true");
      return;
    }

    if (dropdown) {
      var wrapper = closest(dropdown, ".yii-debug-dropdown");
      var menu = wrapper
        ? wrapper.querySelector(".yii-debug-dropdown-menu")
        : null;
      event.preventDefault();
      event.stopPropagation();

      if (!wrapper || !menu) {
        return;
      }

      var isOpen = wrapper.classList.contains("is-open");
      hideDropdowns(menu);
      wrapper.classList.toggle("is-open", !isOpen);
      dropdown.setAttribute("aria-expanded", isOpen ? "false" : "true");
      return;
    }

    hideDropdowns(null);
  });

  document.addEventListener("keydown", function (event) {
    var tab = findToggle(event.target, "tab");

    if (
      tab &&
      ["ArrowLeft", "ArrowRight", "Home", "End"].indexOf(event.key) !== -1
    ) {
      var tabList = closest(tab, '[role="tablist"]');
      var tabs = tabList
        ? Array.from(tabList.querySelectorAll('[data-yii-debug-toggle="tab"]'))
        : [];
      var current = tabs.indexOf(tab);
      var next = current;

      if (event.key === "Home") {
        next = 0;
      } else if (event.key === "End") {
        next = tabs.length - 1;
      } else if (event.key === "ArrowLeft") {
        next = (current - 1 + tabs.length) % tabs.length;
      } else if (event.key === "ArrowRight") {
        next = (current + 1) % tabs.length;
      }

      if (tabs[next]) {
        event.preventDefault();
        activateTab(tabs[next]);
        tabs[next].focus();
      }

      return;
    }

    if (event.key === "Escape") {
      hideDropdowns(null);
    }
  });

  // Click-to-reveal toggle for sensitive User-panel fields.
  document.addEventListener("click", function (event) {
    var btn = event.target.closest("[data-yii-debug-reveal]");

    if (!btn) {
      return;
    }

    btn.classList.toggle("is-revealed");
    btn.setAttribute(
      "aria-pressed",
      btn.classList.contains("is-revealed") ? "true" : "false",
    );
  });

  // Page-size selector inside GridView footers. Picks up the change event,
  // rewrites the `per-page` query param while keeping every other filter/sort
  // intact, and reloads the panel.
  document.addEventListener("change", function (event) {
    var select = event.target;

    if (!select || !select.matches("[data-yii-debug-pagesize]")) {
      return;
    }

    var url = new URL(window.location.href);

    if (select.value === "" || select.value === "0") {
      url.searchParams.delete("per-page");
    } else {
      url.searchParams.set("per-page", select.value);
    }

    // Drop the page param so we land on page 1 with the new size.
    url.searchParams.delete("page");
    window.location.href = url.toString();
  });

  // Live filter for tabular sections marked with [data-yii-debug-filter].
  // The input filters its sibling [data-yii-debug-filter-target] table rows by
  // case-insensitive substring against the row's text content. Hiding rows
  // client-side is cheap and avoids round-trips for >100-header request panels.
  document.addEventListener("input", function (event) {
    var input = event.target;

    if (!input || !input.matches("[data-yii-debug-filter]")) {
      return;
    }

    // Target is the closest following sibling block that opted in via
    // [data-yii-debug-filter-target]. Walking from the input's wrapper keeps
    // each filter scoped to its own table when several share a tab.
    var anchor = input.closest("header, .yii-debug-section-header") || input;
    var target = anchor.nextElementSibling;

    while (target && !target.matches("[data-yii-debug-filter-target]")) {
      target = target.nextElementSibling;
    }

    if (!target) {
      return;
    }

    var rows = target.querySelectorAll("tbody tr");
    var query = input.value.trim().toLowerCase();

    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      if (query === "") {
        row.hidden = false;
        continue;
      }
      row.hidden = row.textContent.toLowerCase().indexOf(query) === -1;
    }
  });

  // GridView filter row → URL bridge. The 22.0 shell ships without jQuery / yii.gridView.js,
  // so each filter input drives URL params by hand. The regex matches any Yii form name
  // pattern `<FormName>[<attr>]`, which means the bridge works for the index page (Debug[…])
  // and every panel (Db[…], Log[…], Profile[…], Event[…], Mail[…], User[…], …) without
  // per-page wiring. <select> filters apply on change, text inputs apply on Enter
  // (immediate), on blur (when the dev tabs out), and after a 400 ms idle while typing.
  // Each apply rebuilds the URL keeping every other query param intact and drops the page
  // param so we always land on page 1.
  (function () {
    var IDLE_MS = 400;
    var FORM_INPUT = /^[A-Za-z][A-Za-z0-9_]*\[[^\]]+\]$/;
    var pending = null;

    function nameMatchesFilter(input) {
      return !!input && !!input.name && FORM_INPUT.test(input.name);
    }

    function apply(input) {
      if (!nameMatchesFilter(input)) {
        return;
      }

      var url = new URL(window.location.href);

      if (input.value === "" || input.value === null) {
        url.searchParams.delete(input.name);
      } else {
        url.searchParams.set(input.name, input.value);
      }

      url.searchParams.delete("page");

      if (url.toString() === window.location.href) {
        return;
      }

      window.location.href = url.toString();
    }

    function scheduleApply(input) {
      if (pending) {
        clearTimeout(pending.timeout);
      }
      pending = {
        input: input,
        timeout: setTimeout(function () {
          var current = pending;
          pending = null;
          apply(current.input);
        }, IDLE_MS),
      };
    }

    function flushPending() {
      if (!pending) {
        return false;
      }
      clearTimeout(pending.timeout);
      var input = pending.input;
      pending = null;
      apply(input);
      return true;
    }

    document.addEventListener("change", function (event) {
      if (
        event.target.tagName === "SELECT" &&
        nameMatchesFilter(event.target)
      ) {
        apply(event.target);
      }
    });

    document.addEventListener("input", function (event) {
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      scheduleApply(event.target);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") {
        return;
      }
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      event.preventDefault();
      if (!flushPending()) {
        apply(event.target);
      }
    });

    document.addEventListener("focusout", function (event) {
      if (event.target.tagName !== "INPUT" || event.target.type === "submit") {
        return;
      }
      if (!nameMatchesFilter(event.target)) {
        return;
      }
      // If the dev tabs out before the debounce fires, flush immediately so the
      // URL reflects whatever they typed.
      if (pending && pending.input === event.target) {
        flushPending();
      }
    });
  })();
})();
