import {
  closest,
  escapeHtml,
  requestStack,
  sameUrl,
  storageKey,
  themeAttributeFilter,
  themeStorageKey,
  toolbars,
} from "./state.js";
import {
  addThemeToUrl,
  getComputedTheme,
  getElementTheme,
  getStorageTheme,
  hostHasThemeControl,
  normalizeTheme,
  writeThemeCookie,
} from "./theme.js";

/**
 * Styles injected into the toolbar's open shadow DOM. Authored in
 * toolbar-shadow.css (shared design tokens via tokens.css) and inlined into
 * this bundle at build time so the Web Component remains self-contained and
 * isolated from the host page's CSS.
 */
import toolbarStyles from "./toolbar-shadow.css?inline";

export function YiiDebugToolbar() {
  var self = Reflect.construct(HTMLElement, [], YiiDebugToolbar);
  self.attachShadow({ mode: "open" });
  self.data = null;
  self.ajaxRequests = requestStack;
  self.activeUrl = "";
  self.expanded =
    window.localStorage && localStorage.getItem(storageKey) === "1";
  self.drawerOpen = false;
  self.resizing = false;
  self.boundPointerMove = self.onPointerMove.bind(self);
  self.boundPointerUp = self.onPointerUp.bind(self);
  self.boundThemeRefresh = self.refreshTheme.bind(self);
  self.theme = null;
  self.themeObserver = null;
  self.systemThemeQuery = null;
  /* Decided lazily in connectedCallback once the host DOM is available. */
  self.ownsTheme = false;

  return self;
}

YiiDebugToolbar.prototype = Object.create(HTMLElement.prototype);
YiiDebugToolbar.prototype.constructor = YiiDebugToolbar;
Object.setPrototypeOf(YiiDebugToolbar, HTMLElement);

YiiDebugToolbar.prototype.connectedCallback = function () {
  if (toolbars.indexOf(this) === -1) {
    toolbars.push(this);
  }

  this.ownsTheme = !hostHasThemeControl();
  this.refreshTheme();
  this.watchTheme();
  this.style.display = "block";
  this.load();

  /**
   * SPA hosts (Vue/Inertia/React) mount their theme switcher AFTER this
   * callback runs, so the initial `hostHasThemeControl()` sweep can miss it.
   * Re-evaluate once the page settles — `refreshTheme()` recomputes
   * `ownsTheme` on every pass.
   */
  window.addEventListener("load", this.boundThemeRefresh, false);
  window.setTimeout(this.boundThemeRefresh, 1500);
};

YiiDebugToolbar.prototype.disconnectedCallback = function () {
  var index = toolbars.indexOf(this);
  if (index !== -1) {
    toolbars.splice(index, 1);
  }

  if (this.themeObserver) {
    this.themeObserver.disconnect();
    this.themeObserver = null;
  }

  if (this.systemThemeQuery) {
    if (this.systemThemeQuery.removeEventListener) {
      this.systemThemeQuery.removeEventListener(
        "change",
        this.boundThemeRefresh,
      );
    } else if (this.systemThemeQuery.removeListener) {
      this.systemThemeQuery.removeListener(this.boundThemeRefresh);
    }
    this.systemThemeQuery = null;
  }

  window.removeEventListener("storage", this.boundThemeRefresh, false);
  window.removeEventListener("load", this.boundThemeRefresh, false);

  if (this.boundThemeMessage) {
    window.removeEventListener("message", this.boundThemeMessage, false);
    this.boundThemeMessage = null;
  }
};

YiiDebugToolbar.prototype.setAjaxRequests = function (requests) {
  this.ajaxRequests = requests;
  this.render();
};

YiiDebugToolbar.prototype.detectTheme = function () {
  /**
   * The current DOM state (`<html>`/`<body>` class or `data-theme` attr) is
   * the most authoritative signal — if the page IS rendering with a Tailwind
   * `dark` class then any stale `localStorage[yii-debug-toolbar-theme]` from
   * a previous session must lose.
   */
  var domTheme =
    getElementTheme(document.documentElement) || getElementTheme(document.body);

  if (domTheme) {
    return domTheme;
  }

  /**
   * The host manages its own theme and the document carries no marker: every
   * mainstream dark-mode convention (Tailwind class, `data-bs-theme`, Pico)
   * marks DARK explicitly, so an unmarked document is the host's light
   * state. Stale storage must not outvote what the page actually renders.
   */
  if (!this.ownsTheme) {
    return getComputedTheme() || "light";
  }

  return (
    normalizeTheme(this.getAttribute("data-yii-debug-theme")) ||
    (window.localStorage
      ? normalizeTheme(localStorage.getItem(themeStorageKey))
      : null) ||
    getStorageTheme() ||
    getComputedTheme() ||
    (window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light")
  );
};

YiiDebugToolbar.prototype.toggleTheme = function () {
  var next = this.theme === "dark" ? "light" : "dark";

  this.theme = next;
  this.setAttribute("data-theme", next);
  /* Pin the explicit choice so `detectTheme()` keeps honoring it. */
  this.setAttribute("data-yii-debug-theme", next);

  if (window.localStorage) {
    try {
      localStorage.setItem(themeStorageKey, next);
    } catch (_e) {}
  }

  /**
   * Cookie is the backend's source of truth — write it so the next panel
   * navigation renders the matching theme even when the URL is bare.
   */
  writeThemeCookie(next);

  /**
   * Fan the change out to the surrounding page — covers Tailwind's `dark`
   * class on <html>, `data-theme`/`data-bs-theme` (Pico/Bootstrap), and the
   * common storage keys host apps read on boot. Even when the host has its
   * own switcher, this keeps the two in sync after the dev clicks ours.
   */
  this.propagateThemeToHost(next);
  this.render();
};

/**
 * When the dev flips the theme via our own toggle (i.e. the host app does
 * NOT ship a switcher of its own) we best-effort fan the change out to the
 * signals most front-end stacks read so the surrounding page also flips.
 * None of these writes is destructive: if a token isn't recognized by the
 * host, it's simply ignored.
 */
YiiDebugToolbar.prototype.propagateThemeToHost = function (theme) {
  var html = document.documentElement;
  var opposite = theme === "dark" ? "light" : "dark";
  var storageKeys = [
    "theme",
    "color-theme",
    "color-scheme",
    "vueuse-color-scheme",
    "vite-ui-theme",
  ];
  var i;

  if (html) {
    /**
     * Tailwind-style modifier class (`<html class="dark">`) is the most
     * common convention; we keep `light`/`dark` mutually exclusive.
     */
    if (html.classList) {
      html.classList.add(theme);
      html.classList.remove(opposite);
    }
    /* Bootstrap 5 / Pico / generic CSS-token convention. */
    html.setAttribute("data-theme", theme);
    html.setAttribute("data-bs-theme", theme);
    html.style.colorScheme = theme;
  }

  if (window.localStorage) {
    for (i = 0; i < storageKeys.length; i++) {
      try {
        localStorage.setItem(storageKeys[i], theme);
      } catch (_e) {
        /* Storage write blocked (private mode, quota) — ignore silently. */
      }
    }
  }
};

YiiDebugToolbar.prototype.refreshTheme = function () {
  /* SPA hosts mount their switcher late — re-evaluate on every pass. */
  this.ownsTheme = !hostHasThemeControl();

  var theme = this.detectTheme();
  var previousTheme = this.theme;

  if (previousTheme === theme) {
    return;
  }

  this.theme = theme;
  this.setAttribute("data-theme", theme);

  if (window.localStorage) {
    localStorage.setItem(themeStorageKey, theme);
  }

  /**
   * Cookie is what the backend reads on the next debug request, so the panel
   * page renders with the correct theme even when the toolbar followed a
   * host change via the MutationObserver and the URL didn't carry
   * `yii_debug_theme`.
   */
  writeThemeCookie(theme);

  if (previousTheme && this.data) {
    this.render();
  }
};

YiiDebugToolbar.prototype.watchTheme = function () {
  var self = this;

  if (window.MutationObserver && !this.themeObserver) {
    this.themeObserver = new MutationObserver(function () {
      self.refreshTheme();
    });

    this.themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: themeAttributeFilter,
    });
    if (document.body) {
      this.themeObserver.observe(document.body, {
        attributes: true,
        attributeFilter: themeAttributeFilter,
      });
    }
  }

  if (window.matchMedia && !this.systemThemeQuery) {
    this.systemThemeQuery = window.matchMedia("(prefers-color-scheme: dark)");
    if (this.systemThemeQuery.addEventListener) {
      this.systemThemeQuery.addEventListener("change", this.boundThemeRefresh);
    } else if (this.systemThemeQuery.addListener) {
      this.systemThemeQuery.addListener(this.boundThemeRefresh);
    }
  }

  window.addEventListener("storage", this.boundThemeRefresh, false);

  /**
   * Receive theme flips from inside the panel iframe (the chip in the panel
   * header postMessages us) and apply them on the host instantly, without
   * waiting for the storage event.
   */
  if (!this.boundThemeMessage) {
    this.boundThemeMessage = function (event) {
      var data = event && event.data;

      if (
        !data ||
        typeof data !== "object" ||
        data.source !== "yii-debug-toolbar" ||
        data.type !== "theme"
      ) {
        return;
      }

      var nextTheme = normalizeTheme(data.theme);

      if (!nextTheme || nextTheme === self.theme) {
        return;
      }

      self.theme = nextTheme;
      self.setAttribute("data-theme", nextTheme);
      /* Pin the explicit choice so `detectTheme()` keeps honoring it. */
      self.setAttribute("data-yii-debug-theme", nextTheme);

      if (window.localStorage) {
        try {
          localStorage.setItem(themeStorageKey, nextTheme);
        } catch (_e) {}
      }

      /**
       * The flip originated inside the panel iframe; carry it to the cookie
       * so a fresh panel navigation (or a hard reload) lands on the same
       * theme.
       */
      writeThemeCookie(nextTheme);

      self.propagateThemeToHost(nextTheme);

      if (self.data) {
        self.render();
      }
    };
    window.addEventListener("message", this.boundThemeMessage, false);
  }
};

YiiDebugToolbar.prototype.withTheme = function (url) {
  var theme = this.theme || this.detectTheme();

  /**
   * Keep the cookie in lockstep with what the drawer is about to show, so
   * bare in-panel navigation (which resolves via the cookie) stays on the
   * same theme as the stamped entry URL.
   */
  writeThemeCookie(theme);

  return addThemeToUrl(url, theme);
};

YiiDebugToolbar.prototype.followTag = function (tag) {
  if (!tag || this.currentTag === tag) {
    return;
  }

  var url = this.getAttribute("data-url");

  if (!url) {
    return;
  }

  var previousUrl = url;
  var previousTag = this.currentTag;

  this.currentTag = tag;
  this.setAttribute(
    "data-url",
    url.replace(/([?&]tag=)[^&]+/, "$1" + encodeURIComponent(tag)),
  );
  this.load(function (ok) {
    if (ok) {
      return;
    }

    /**
     * The tag we tried to follow was rejected (404 — rotated out of history,
     * 500, etc.). Roll back so the toolbar keeps showing the last good data
     * instead of leaving the user with a broken state.
     */
    this.currentTag = previousTag;
    this.setAttribute("data-url", previousUrl);
  });
};

YiiDebugToolbar.prototype.load = function (done) {
  var self = this;
  var url = this.getAttribute("data-url");
  var notify = function (ok) {
    if (typeof done === "function") {
      done.call(self, ok);
    }
  };

  if (!url) {
    this.renderError("Debug toolbar data URL is missing.");
    notify(false);
    return;
  }

  var xhr = new XMLHttpRequest();
  xhr.open("GET", url, true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
  xhr.setRequestHeader("Accept", "application/json");
  xhr.onreadystatechange = function () {
    if (xhr.readyState !== 4) {
      return;
    }

    if (xhr.status !== 200) {
      /**
       * Don't render an error for stale-tag fetches that the caller is ready
       * to recover from; just signal failure.
       */
      if (typeof done !== "function") {
        var message;
        if (xhr.status === 404) {
          /**
           * Request was profiled but its tag has rotated out of the debug
           * history (or never made it to the manifest). Don't dump the raw
           * JSON body at the user.
           */
          message = "Debug data is no longer available for this request.";
        } else {
          /**
           * Try to read a structured `{error: "..."}` payload first, then
           * fall back to a generic message instead of leaking raw HTML/JSON.
           */
          try {
            var parsed = JSON.parse(xhr.responseText);
            message =
              (parsed && parsed.error) || "Unable to load debug toolbar data.";
          } catch {
            message = "Unable to load debug toolbar data.";
          }
        }
        self.renderError(message);
      }
      notify(false);
      return;
    }

    try {
      self.data = JSON.parse(xhr.responseText);
    } catch {
      self.renderError("Invalid debug toolbar data response.");
      notify(false);
      return;
    }

    self.render();
    self.dispatchAttachedEvent();
    notify(true);
  };
  xhr.send();
};

YiiDebugToolbar.prototype.dispatchAttachedEvent = function () {
  var event;

  if (typeof Event === "function") {
    event = new Event("yii.debug.toolbar_attached", { bubbles: true });
  } else {
    event = document.createEvent("Event");
    event.initEvent("yii.debug.toolbar_attached", true, true);
  }

  this.dispatchEvent(event);
};

YiiDebugToolbar.prototype.ensureShadowSkeleton = function () {
  if (this.contentRoot) {
    return;
  }

  var style = document.createElement("style");
  style.textContent = toolbarStyles;
  this.shadowRoot.appendChild(style);

  this.contentRoot = document.createElement("div");
  this.contentRoot.style.display = "contents";
  this.shadowRoot.appendChild(this.contentRoot);

  this.bindDelegatedEvents();
};

YiiDebugToolbar.prototype.renderError = function (message) {
  this.ensureShadowSkeleton();
  this.contentRoot.innerHTML =
    '<div class="toolbar expanded"><div class="bar"><strong>Yii Debugger</strong>' +
    '<span class="error-message">' +
    escapeHtml(message) +
    "</span></div></div>";
};

YiiDebugToolbar.prototype.render = function () {
  this.ensureShadowSkeleton();

  if (!this.data) {
    this.contentRoot.innerHTML =
      '<div class="toolbar loading"><button type="button" class="brand">Yii Debugger</button></div>';
    return;
  }

  var position =
    this.getAttribute("data-position") || this.data.position || "bottom";
  var classes = ["toolbar", "position-" + position];

  if (this.expanded) {
    classes.push("expanded");
  }
  if (this.drawerOpen) {
    classes.push("drawer-open");
  }

  var profilingPanel = (this.data.items || []).find(function (p) {
    return p && p.id === "profiling";
  });
  var profilingChip = profilingPanel ? this.renderPanel(profilingPanel) : "";

  this.contentRoot.innerHTML =
    '<div class="' +
    classes.join(" ") +
    '">' +
    '<div class="bar">' +
    (this.expanded
      ? this.renderBrand() +
        this.renderPhpChip() +
        profilingChip +
        this.renderAjaxPanel() +
        this.renderPanels(["profiling"]) +
        this.renderControls()
      : this.renderCollapsedOpener()) +
    "</div>" +
    this.renderDrawer(position) +
    "</div>";

  this.bindEvents();
  this.applyDrawerHeight();
};

YiiDebugToolbar.prototype.renderLogo = function () {
  var src = this.data.logo || this.data.logoFallback;
  return src
    ? '<img src="' + escapeHtml(src) + '" alt="" width="18" height="18">'
    : '<span class="brand-mark">Y</span>';
};

YiiDebugToolbar.prototype.renderCollapsedOpener = function () {
  var title = escapeHtml(this.data.title || "Yii Debugger");

  return (
    '<button type="button" class="brand brand-opener toggle-toolbar" title="Expand debug toolbar" aria-label="Expand debug toolbar">' +
    this.renderLogo() +
    '<span class="brand-text">' +
    title +
    '</span><span class="opener-icon">›</span></button>'
  );
};

YiiDebugToolbar.prototype.renderBrand = function () {
  var configUrl = this.data.configUrl || this.data.indexUrl;
  var yiiAttr = configUrl
    ? ' data-debug-url="' + escapeHtml(this.withTheme(configUrl)) + '"'
    : "";
  var yiiVersion = this.data.yiiVersion
    ? '<span class="brand-version">' +
      escapeHtml(this.data.yiiVersion) +
      "</span>"
    : "";
  var yiiTitle = this.data.yiiVersion
    ? "Yii " + this.data.yiiVersion + " — open configuration"
    : "Open configuration";

  var yiiLink =
    '<span class="brand-link brand-link-yii" role="button" tabindex="0"' +
    yiiAttr +
    ' title="' +
    escapeHtml(yiiTitle) +
    '">' +
    this.renderLogo() +
    yiiVersion +
    "</span>";

  var phpLink = "";
  if (this.data.phpVersion) {
    var phpUrl = this.data.phpInfoUrl
      ? escapeHtml(this.withTheme(this.data.phpInfoUrl))
      : "#";
    var phpTitle =
      "PHP " + this.data.phpVersion + " — open phpinfo in a new tab";
    phpLink =
      '<a class="brand-link brand-link-php" href="' +
      phpUrl +
      '" target="_blank" rel="noopener" title="' +
      escapeHtml(phpTitle) +
      '">' +
      this.iconHtml("php-alt", "panel-icon panel-icon-php") +
      '<span class="brand-version">' +
      escapeHtml(this.data.phpVersion) +
      "</span>" +
      "</a>";
  }

  var divider = phpLink
    ? '<span class="brand-divider" aria-hidden="true"></span>'
    : "";

  return '<div class="brand">' + yiiLink + divider + phpLink + "</div>";
};

YiiDebugToolbar.prototype.renderPhpChip = function () {
  return "";
};

YiiDebugToolbar.prototype.renderAjaxPanel = function () {
  var status = "success";
  var requests = this.ajaxRequests || [];
  var recent = requests.slice(Math.max(0, requests.length - 20));
  var rows = "";
  var icon = this.iconHtml("ajax", "panel-icon");

  requests.forEach(function (request, index) {
    if (request.loading) {
      status = "loading";
    } else if (request.error && index > requests.length - 4) {
      status = "danger";
    }
  });

  recent.forEach(function (request) {
    var requestStatus = request.loading
      ? "loading"
      : request.error
        ? "danger"
        : "success";
    var profile = request.profilerUrl
      ? '<button type="button" class="ajax-link" data-debug-url="' +
        escapeHtml(request.profilerUrl) +
        '">' +
        escapeHtml(request.profile || "profile") +
        "</button>"
      : "n/a";

    rows +=
      "<tr><td>" +
      escapeHtml(request.method || "GET") +
      "</td>" +
      '<td><span class="badge badge-' +
      requestStatus +
      '">' +
      escapeHtml(request.statusCode || "-") +
      "</span></td>" +
      '<td class="ajax-url" title="' +
      escapeHtml(request.url) +
      '">' +
      escapeHtml(request.url) +
      "</td>" +
      "<td>" +
      escapeHtml(request.duration ? request.duration + " ms" : "-") +
      "</td>" +
      "<td>" +
      profile +
      "</td></tr>";
  });

  if (rows === "") {
    rows =
      '<tr><td colspan="5" class="empty">No AJAX requests tracked yet.</td></tr>';
  }

  return (
    '<div class="panel ajax-panel">' +
    icon +
    '<span class="panel-title">AJAX</span>' +
    '<span class="metric"><span class="metric-value badge-' +
    status +
    '">' +
    requests.length +
    "</span></span>" +
    '<div class="ajax-popover"><table><thead><tr><th>Method</th><th>Status</th><th>URL</th><th>Time</th><th>Profile</th></tr></thead>' +
    "<tbody>" +
    rows +
    "</tbody></table></div></div>"
  );
};

YiiDebugToolbar.prototype.renderPanels = function (excludeIds) {
  var html = "";
  var items = this.data.items || [];
  var exclude = excludeIds || [];

  items.forEach(function (panel) {
    if (panel && exclude.indexOf(panel.id) !== -1) {
      return;
    }
    html += this.renderPanel(panel);
  }, this);

  return '<div class="panels">' + html + "</div>";
};

YiiDebugToolbar.prototype.isPanelActive = function (panel) {
  var items = panel.items || [];

  if (!this.activeUrl) {
    return false;
  }
  if (panel.url && sameUrl(panel.url, this.activeUrl)) {
    return true;
  }

  for (var i = 0; i < items.length; i++) {
    if (items[i].url && sameUrl(items[i].url, this.activeUrl)) {
      return true;
    }
  }

  return false;
};

YiiDebugToolbar.prototype.iconHtml = function (iconName, cls) {
  if (!iconName || !this.data || !this.data.iconBaseUrl) {
    return "";
  }
  var url = this.data.iconBaseUrl + iconName + ".svg";
  var escaped = escapeHtml(url);
  return (
    '<span class="' +
    cls +
    '" aria-hidden="true" style="-webkit-mask-image:url(' +
    escaped +
    ");mask-image:url(" +
    escaped +
    ')"></span>'
  );
};

YiiDebugToolbar.prototype.renderPanel = function (panel) {
  if (panel.html) {
    return '<div class="panel legacy-panel">' + panel.html + "</div>";
  }

  var metrics = "";
  var items = panel.items || [];
  var rawTitle =
    typeof panel.title === "string" ? panel.title : panel.id || "Panel";
  var hasTitle = rawTitle !== "";
  var attrTitle = hasTitle ? rawTitle : panel.id || "Panel";
  var url = panel.url ? ' data-debug-url="' + escapeHtml(panel.url) + '"' : "";
  var panelClass = this.isPanelActive(panel) ? " panel-active" : "";
  var self = this;

  items.forEach(function (item) {
    var status = item.status || "default";
    var itemUrl = item.url
      ? ' data-debug-url="' + escapeHtml(item.url) + '"'
      : "";
    var itemTitle = item.title ? ' title="' + escapeHtml(item.title) + '"' : "";
    var metricClass =
      item.url && sameUrl(item.url, this.activeUrl) ? " metric-active" : "";

    metrics +=
      '<span class="metric' + metricClass + '"' + itemUrl + itemTitle + ">";
    if (item.icon) {
      metrics += self.iconHtml(item.icon, "metric-icon");
    } else if (item.label) {
      metrics +=
        '<span class="metric-label">' + escapeHtml(item.label) + "</span>";
    }
    metrics +=
      '<span class="metric-value badge-' +
      escapeHtml(status) +
      '">' +
      escapeHtml(item.value) +
      "</span></span>";
  }, this);

  var iconHtml = panel.icon ? this.iconHtml(panel.icon, "panel-icon") : "";

  return (
    '<div class="panel' +
    panelClass +
    '" role="button" tabindex="0" title="' +
    escapeHtml(attrTitle) +
    '"' +
    url +
    ">" +
    iconHtml +
    (hasTitle
      ? '<span class="panel-title">' + escapeHtml(rawTitle) + "</span>"
      : "") +
    metrics +
    "</div>"
  );
};

YiiDebugToolbar.prototype.renderControls = function () {
  var external = this.activeUrl
    ? '<a class="control" href="' +
      escapeHtml(this.withTheme(this.activeUrl)) +
      '" target="_blank" rel="noopener" title="Open panel in a new tab">↗</a>'
    : '<span class="control disabled" title="Open a panel first">↗</span>';
  var drawer = this.drawerOpen
    ? '<button type="button" class="control close-drawer" title="Close panel" aria-label="Close panel">×</button>'
    : "";
  var toggleTitle = this.expanded ? "Collapse toolbar" : "Expand toolbar";
  var toggleText = this.expanded ? "›" : "‹";

  var nextTheme = this.theme === "dark" ? "light" : "dark";
  var themeLabel = "Switch to " + nextTheme + " theme";
  /**
   * Show the icon that represents the *next* theme — click moves you toward
   * what you see. Reuses the same `mask-image` pipeline as the panel chips
   * so the glyph picks up `currentColor`.
   */
  var themeIcon = this.iconHtml(
    this.theme === "dark" ? "sun" : "moon",
    "control-icon",
  );
  var themeControl =
    '<button type="button" class="control toggle-theme" title="' +
    themeLabel +
    '" aria-label="' +
    themeLabel +
    '">' +
    themeIcon +
    "</button>";

  return (
    '<div class="controls">' +
    themeControl +
    external +
    drawer +
    '<button type="button" class="control toggle-toolbar" title="' +
    toggleTitle +
    '" aria-label="' +
    toggleTitle +
    '">' +
    toggleText +
    "</button></div>"
  );
};

YiiDebugToolbar.prototype.renderDrawer = function (position) {
  if (!this.drawerOpen || !this.activeUrl) {
    return "";
  }

  var handle =
    '<div class="resize-handle" title="Resize debug panel" aria-label="Resize debug panel"></div>';
  var drawer =
    '<div class="drawer"><iframe src="' +
    escapeHtml(this.withTheme(this.activeUrl)) +
    '" title="Yii debug panel"></iframe></div>';

  return position === "upper" ? drawer + handle : handle + drawer;
};

YiiDebugToolbar.prototype.bindDelegatedEvents = function () {
  var root = this.shadowRoot;
  var self = this;

  root.addEventListener("click", function (event) {
    var target =
      closest(event.target, "[data-debug-url]") ||
      closest(event.target, ".legacy-panel a[href]");
    var url = target
      ? target.getAttribute("data-debug-url") || target.getAttribute("href")
      : null;

    if (!url || event.button === 1 || event.ctrlKey || event.metaKey) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    self.openPanel(url);
  });

  root.addEventListener("keydown", function (event) {
    var target =
      closest(event.target, "[data-debug-url]") ||
      closest(event.target, ".legacy-panel a[href]");
    var url = target
      ? target.getAttribute("data-debug-url") || target.getAttribute("href")
      : null;

    if (!url || (event.key !== "Enter" && event.key !== " ")) {
      return;
    }

    event.preventDefault();
    self.openPanel(url);
  });
};

YiiDebugToolbar.prototype.bindEvents = function () {
  var root = this.shadowRoot;
  var toggle = root.querySelector(".toggle-toolbar");
  var toggleTheme = root.querySelector(".toggle-theme");
  var closeDrawer = root.querySelector(".close-drawer");
  var resizeHandle = root.querySelector(".resize-handle");
  var self = this;

  if (toggle) {
    toggle.addEventListener("click", function () {
      self.toggleExpanded();
    });
  }

  if (toggleTheme) {
    toggleTheme.addEventListener("click", function () {
      self.toggleTheme();
    });
  }

  if (closeDrawer) {
    closeDrawer.addEventListener("click", function () {
      self.closeDrawer();
    });
  }

  if (resizeHandle) {
    resizeHandle.addEventListener(
      "pointerdown",
      function (event) {
        self.resizing = true;
        event.preventDefault();
        document.addEventListener("pointermove", self.boundPointerMove, false);
        document.addEventListener("pointerup", self.boundPointerUp, false);
      },
      false,
    );
  }
};

YiiDebugToolbar.prototype.toggleExpanded = function () {
  this.expanded = !this.expanded;
  if (window.localStorage) {
    localStorage.setItem(storageKey, this.expanded ? "1" : "0");
  }
  if (!this.expanded) {
    this.drawerOpen = false;
  }
  this.render();
};

YiiDebugToolbar.prototype.openPanel = function (url) {
  if (!url) {
    return;
  }

  this.expanded = true;
  this.drawerOpen = true;
  this.activeUrl = url;
  if (window.localStorage) {
    localStorage.setItem(storageKey, "1");
  }
  this.render();
};

YiiDebugToolbar.prototype.closeDrawer = function () {
  this.drawerOpen = false;
  this.render();
};

YiiDebugToolbar.prototype.applyDrawerHeight = function () {
  var drawer = this.shadowRoot.querySelector(".drawer");

  if (!drawer) {
    return;
  }

  if (!this.style.getPropertyValue("--yii-debug-toolbar-drawer-height")) {
    var height = parseInt(
      this.getAttribute("data-height") || this.data.defaultHeight || 50,
      10,
    );
    this.style.setProperty(
      "--yii-debug-toolbar-drawer-height",
      Math.max(20, Math.min(90, height)) + "vh",
    );
  }
};

YiiDebugToolbar.prototype.onPointerMove = function (event) {
  if (!this.resizing) {
    return;
  }

  var position =
    this.getAttribute("data-position") || this.data.position || "bottom";
  var drawer = this.shadowRoot.querySelector(".drawer");
  var viewportHeight =
    window.innerHeight || document.documentElement.clientHeight;
  var drawerRect = drawer ? drawer.getBoundingClientRect() : null;
  var height =
    drawerRect === null
      ? position === "upper"
        ? event.clientY
        : viewportHeight - event.clientY
      : position === "upper"
        ? event.clientY - drawerRect.top
        : drawerRect.bottom - event.clientY;

  this.style.setProperty(
    "--yii-debug-toolbar-drawer-height",
    Math.max(120, Math.min(viewportHeight - 48, height)) + "px",
  );
};

YiiDebugToolbar.prototype.onPointerUp = function () {
  this.resizing = false;
  document.removeEventListener("pointermove", this.boundPointerMove, false);
  document.removeEventListener("pointerup", this.boundPointerUp, false);
};

YiiDebugToolbar.prototype.getStyles = function () {
  return toolbarStyles;
};
