(function () {
  "use strict";

  var themeParam = "yii_debug_theme";
  var themeStorageKey = "yii-debug-toolbar-theme";
  var legacyToggleWarned = false;

  function normalizeTheme(value) {
    var theme;

    if (!value) {
      return null;
    }

    theme = String(value).toLowerCase();

    if (theme === "dark" || theme === "night" || theme === "black") {
      return "dark";
    }

    if (theme === "light" || theme === "day" || theme === "white") {
      return "light";
    }

    if (theme.indexOf("dark") !== -1 && theme.indexOf("light") === -1) {
      return "dark";
    }

    if (theme.indexOf("light") !== -1 && theme.indexOf("dark") === -1) {
      return "light";
    }

    return null;
  }

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

  function getStoredTheme() {
    if (!window.localStorage) {
      return null;
    }

    return normalizeTheme(localStorage.getItem(themeStorageKey));
  }

  function getUrlTheme() {
    try {
      return normalizeTheme(
        new URL(window.location.href).searchParams.get(themeParam),
      );
    } catch {
      return null;
    }
  }

  function addThemeToUrl(url, theme) {
    var parsed;

    if (!theme) {
      return url;
    }

    try {
      parsed = new URL(url, window.location.href);
    } catch {
      return url;
    }

    if (
      parsed.origin !== window.location.origin ||
      parsed.pathname.indexOf("/debug/") === -1
    ) {
      return url;
    }

    parsed.searchParams.set(themeParam, theme);

    return parsed.href;
  }

  function applyTheme() {
    var theme =
      getUrlTheme() ||
      normalizeTheme(
        document.documentElement.getAttribute("data-yii-debug-theme"),
      ) ||
      getParentToolbarTheme() ||
      getStoredTheme() ||
      (window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light");

    document.documentElement.setAttribute("data-yii-debug-theme", theme);

    if (window.localStorage) {
      localStorage.setItem(themeStorageKey, theme);
    }

    document.cookie =
      themeStorageKey +
      "=" +
      encodeURIComponent(theme) +
      "; path=/; SameSite=Lax";

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
        links[i].setAttribute("href", addThemeToUrl(href, theme));
      }
    }

    for (i = 0; i < forms.length; i++) {
      forms[i].setAttribute(
        "action",
        addThemeToUrl(
          forms[i].getAttribute("action") || window.location.href,
          theme,
        ),
      );

      if ((forms[i].getAttribute("method") || "get").toLowerCase() !== "get") {
        continue;
      }

      input = forms[i].querySelector('input[name="' + themeParam + '"]');
      if (!input) {
        input = document.createElement("input");
        input.type = "hidden";
        input.name = themeParam;
        forms[i].appendChild(input);
      }
      input.value = theme;
    }
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
    var current = node && node.nodeType !== 1 ? node.parentElement : node;

    while (current && current.nodeType === 1) {
      if (current.getAttribute) {
        if (current.getAttribute("data-yii-debug-toggle") === kind) {
          return current;
        }

        if (current.getAttribute("data-toggle") === kind) {
          if (
            !legacyToggleWarned &&
            typeof console !== "undefined" &&
            console.warn
          ) {
            console.warn(
              "[yii-debug] `data-toggle` is deprecated; use `data-yii-debug-toggle`.",
            );
            legacyToggleWarned = true;
          }
          return current;
        }
      }
      current = current.parentElement;
    }

    return null;
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
        '[data-yii-debug-toggle="dropdown"], [data-toggle="dropdown"]',
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
      ? list.querySelectorAll(
          '[data-yii-debug-toggle="tab"], [data-toggle="tab"]',
        )
      : [];
    var panes = content ? content.children : [];
    var i;

    for (i = 0; i < links.length; i++) {
      links[i].classList.remove("is-active");
      links[i].setAttribute("aria-selected", "false");
    }

    for (i = 0; i < panes.length; i++) {
      if (
        panes[i].classList &&
        panes[i].classList.contains("yii-debug-tab-panel")
      ) {
        panes[i].classList.remove("is-active");
      }
    }

    link.classList.add("is-active");
    link.setAttribute("aria-selected", "true");
    target.classList.add("is-active");
  }

  preserveThemeInLinks(applyTheme());

  document.addEventListener("click", function (event) {
    var tab = findToggle(event.target, "tab");
    var dropdown = findToggle(event.target, "dropdown");
    var collapse = findToggle(event.target, "collapse");

    if (tab) {
      event.preventDefault();
      activateTab(tab);
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
