(function () {
  "use strict";

  var tagName = "yii-debug-toolbar";
  var storageKey = "yii-debug-toolbar-expanded";
  var themeParam = "yii_debug_theme";
  var themeStorageKey = "yii-debug-toolbar-theme";
  var requestStack = [];
  var requestStackLimit = 100;
  var themeAttributeFilter = [
    "class",
    "data-theme",
    "data-bs-theme",
    "data-yii-debug-theme",
    "data-color-mode",
    "data-mode",
    "data-theme-mode",
  ];
  var toolbars = [];
  var originalXhrOpen = XMLHttpRequest.prototype.open;
  var originalFetch = window.fetch;

  function escapeHtml(value) {
    return String(value === null || typeof value === "undefined" ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function parseJsonAttribute(element, attribute, fallback) {
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

  function getElementTheme(element) {
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

  function getStorageTheme() {
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

  function getComputedTheme() {
    var colorScheme;

    if (!window.getComputedStyle) {
      return null;
    }

    colorScheme =
      getComputedStyle(document.documentElement).colorScheme ||
      (document.body ? getComputedStyle(document.body).colorScheme : "");

    return normalizeTheme(colorScheme);
  }

  function absoluteUrl(url) {
    try {
      return new URL(url, window.location.href);
    } catch {
      return null;
    }
  }

  function addThemeToUrl(url, theme) {
    var parsed = absoluteUrl(url);

    if (!parsed || !theme) {
      return url;
    }

    parsed.searchParams.set(themeParam, theme);

    return parsed.href;
  }

  function sameUrl(left, right) {
    var leftUrl = absoluteUrl(left);
    var rightUrl = absoluteUrl(right);

    return (
      leftUrl !== null && rightUrl !== null && leftUrl.href === rightUrl.href
    );
  }

  function getPrimaryToolbar() {
    return toolbars.length ? toolbars[0] : document.querySelector(tagName);
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

  function shouldTrackRequest(requestUrl) {
    if (!requestUrl) {
      return false;
    }

    var toolbar = getPrimaryToolbar();
    if (!toolbar) {
      return false;
    }

    var url = absoluteUrl(requestUrl);
    if (url === null || url.host !== window.location.host) {
      return false;
    }

    if (sameUrl(requestUrl, toolbar.getAttribute("data-url"))) {
      return false;
    }

    var skipUrls = parseJsonAttribute(toolbar, "data-skip-urls", []);
    for (var i = 0; i < skipUrls.length; i++) {
      if (sameUrl(requestUrl, skipUrls[i])) {
        return false;
      }
    }

    return true;
  }

  function notifyAjaxChange() {
    toolbars.forEach(function (toolbar) {
      toolbar.setAjaxRequests(requestStack);
    });
  }

  function trackXhr() {
    XMLHttpRequest.prototype.open = function (method, url) {
      var xhr = this;

      if (shouldTrackRequest(url)) {
        var item = {
          loading: true,
          error: false,
          url: url,
          method: method,
          start: new Date(),
        };
        requestStack.push(item);
        if (requestStack.length > requestStackLimit) {
          requestStack.splice(0, requestStack.length - requestStackLimit);
        }
        xhr.addEventListener(
          "readystatechange",
          function () {
            if (xhr.readyState === 4) {
              item.duration =
                xhr.getResponseHeader("X-Debug-Duration") ||
                new Date() - item.start;
              item.loading = false;
              item.statusCode = xhr.status;
              item.error = xhr.status < 200 || xhr.status >= 400;
              item.profile = xhr.getResponseHeader("X-Debug-Tag");
              item.profilerUrl = xhr.getResponseHeader("X-Debug-Link");
              notifyAjaxChange();
            }
          },
          false,
        );
        notifyAjaxChange();
      }

      originalXhrOpen.apply(xhr, Array.prototype.slice.call(arguments));
    };
  }

  function trackFetch() {
    if (!originalFetch) {
      return;
    }

    window.fetch = function (input, init) {
      var method;
      var url;

      if (typeof input === "string") {
        method = (init && init.method) || "GET";
        url = input;
      } else if (window.URL && input instanceof URL) {
        method = (init && init.method) || "GET";
        url = input.href;
      } else if (window.Request && input instanceof Request) {
        method = input.method;
        url = input.url;
      }

      var promise = originalFetch(input, init);

      if (shouldTrackRequest(url)) {
        var item = {
          loading: true,
          error: false,
          url: url,
          method: method,
          start: new Date(),
        };
        requestStack.push(item);
        if (requestStack.length > requestStackLimit) {
          requestStack.splice(0, requestStack.length - requestStackLimit);
        }
        promise
          .then(function (response) {
            item.duration =
              response.headers.get("X-Debug-Duration") ||
              new Date() - item.start;
            item.loading = false;
            item.statusCode = response.status;
            item.error = response.status < 200 || response.status >= 400;
            item.profile = response.headers.get("X-Debug-Tag");
            item.profilerUrl = response.headers.get("X-Debug-Link");
            notifyAjaxChange();

            return response;
          })
          .catch(function () {
            item.loading = false;
            item.error = true;
            notifyAjaxChange();
          });
        notifyAjaxChange();
      }

      return promise;
    };
  }

  function trackRequests() {
    if (window.__yiiDebugToolbarTracking) {
      return;
    }

    window.__yiiDebugToolbarTracking = true;
    trackXhr();
    trackFetch();
  }

  if (!window.customElements || window.customElements.get(tagName)) {
    trackRequests();
    return;
  }

  function YiiDebugToolbar() {
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

    return self;
  }

  YiiDebugToolbar.prototype = Object.create(HTMLElement.prototype);
  YiiDebugToolbar.prototype.constructor = YiiDebugToolbar;
  Object.setPrototypeOf(YiiDebugToolbar, HTMLElement);

  YiiDebugToolbar.prototype.connectedCallback = function () {
    if (toolbars.indexOf(this) === -1) {
      toolbars.push(this);
    }

    this.refreshTheme();
    this.watchTheme();
    this.style.display = "block";
    this.load();
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
  };

  YiiDebugToolbar.prototype.setAjaxRequests = function (requests) {
    this.ajaxRequests = requests;
    this.render();
  };

  YiiDebugToolbar.prototype.detectTheme = function () {
    return (
      normalizeTheme(this.getAttribute("data-yii-debug-theme")) ||
      getElementTheme(document.documentElement) ||
      getElementTheme(document.body) ||
      getStorageTheme() ||
      getComputedTheme() ||
      (window.matchMedia &&
      window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "light")
    );
  };

  YiiDebugToolbar.prototype.refreshTheme = function () {
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
        this.systemThemeQuery.addEventListener(
          "change",
          this.boundThemeRefresh,
        );
      } else if (this.systemThemeQuery.addListener) {
        this.systemThemeQuery.addListener(this.boundThemeRefresh);
      }
    }

    window.addEventListener("storage", this.boundThemeRefresh, false);
  };

  YiiDebugToolbar.prototype.withTheme = function (url) {
    return addThemeToUrl(url, this.theme || this.detectTheme());
  };

  YiiDebugToolbar.prototype.load = function () {
    var self = this;
    var url = this.getAttribute("data-url");

    if (!url) {
      this.renderError("Debug toolbar data URL is missing.");
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
        self.renderError(
          xhr.responseText || "Unable to load debug toolbar data.",
        );
        return;
      }

      try {
        self.data = JSON.parse(xhr.responseText);
      } catch {
        self.renderError("Invalid debug toolbar data response.");
        return;
      }

      self.render();
      self.dispatchAttachedEvent();
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
      '<span class="brand__link brand__link--yii" role="button" tabindex="0"' +
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
        '<a class="brand__link brand__link--php" href="' +
        phpUrl +
        '" target="_blank" rel="noopener" title="' +
        escapeHtml(phpTitle) +
        '">' +
        this.iconHtml("php-alt", "panel-icon panel-icon--php") +
        '<span class="brand-version">' +
        escapeHtml(this.data.phpVersion) +
        "</span>" +
        "</a>";
    }

    var divider = phpLink
      ? '<span class="brand__divider" aria-hidden="true"></span>'
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
    var url = panel.url
      ? ' data-debug-url="' + escapeHtml(panel.url) + '"'
      : "";
    var panelClass = this.isPanelActive(panel) ? " panel-active" : "";
    var self = this;

    items.forEach(function (item) {
      var status = item.status || "default";
      var itemUrl = item.url
        ? ' data-debug-url="' + escapeHtml(item.url) + '"'
        : "";
      var itemTitle = item.title
        ? ' title="' + escapeHtml(item.title) + '"'
        : "";
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

    return (
      '<div class="controls">' +
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
    var closeDrawer = root.querySelector(".close-drawer");
    var resizeHandle = root.querySelector(".resize-handle");
    var self = this;

    if (toggle) {
      toggle.addEventListener("click", function () {
        self.toggleExpanded();
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
          document.addEventListener(
            "pointermove",
            self.boundPointerMove,
            false,
          );
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

  var toolbarStyles =
    ':host{--yii-debug-toolbar-drawer-height:50vh;all:initial;color-scheme:light dark;direction:ltr;position:fixed;right:16px;bottom:16px;z-index:2147483647;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;line-height:1.35;color:#d6e2f0}' +
    ':host([data-position="upper"]){top:16px;bottom:auto}.toolbar{max-width:calc(100vw - 32px)}.bar{position:relative;display:flex;align-items:center;gap:6px;max-width:100%;padding:6px;border:1px solid rgba(148,163,184,.24);border-radius:18px;background:rgba(15,23,42,.94);box-shadow:0 20px 60px rgba(2,6,23,.35),0 1px 0 rgba(255,255,255,.08) inset;backdrop-filter:blur(16px);box-sizing:border-box}.expanded .bar{width:calc(100vw - 32px);border-radius:16px;overflow:visible}.drawer-open .bar{border-radius:16px 16px 0 0;border-bottom-color:rgba(148,163,184,.12)}.brand{display:inline-flex;align-items:center;gap:8px;min-height:32px;padding:4px 10px;border:0;border-radius:12px;color:#f8fafc;background:linear-gradient(135deg,#1f2937,#0f172a);text-decoration:none;white-space:nowrap;box-sizing:border-box}.brand-opener{cursor:pointer;box-shadow:0 1px 0 rgba(255,255,255,.08) inset}.brand img,.brand-mark{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px}.brand img{object-fit:contain}.brand-mark{border-radius:8px;background:linear-gradient(135deg,#0ea5e9,#22c55e);color:#fff;font-weight:800}.brand-text{display:none}.brand{gap:0}.brand__link{display:inline-flex;align-items:center;gap:6px;padding:2px 6px;border-radius:10px;color:#f0f6ff;text-decoration:none;cursor:pointer;transition:background .12s ease}.brand__link:hover,.brand__link:focus{background:rgba(255,255,255,.08);color:#fff;text-decoration:none;outline:0}.brand__divider{display:inline-block;width:1px;height:18px;margin:0 4px;background:rgba(255,255,255,.18)}.brand-version{display:inline-flex;align-items:center;min-height:18px;padding:0 7px;border:1px solid rgba(255,255,255,.12);border-radius:999px;background:rgba(255,255,255,.08);color:#f0f6ff;font-size:11px;font-weight:700;letter-spacing:.01em;line-height:18px;white-space:nowrap}.opener-icon{display:inline-flex;align-items:center;justify-content:center;width:18px;height:24px;color:#93c5fd;font-size:20px;line-height:1}.panels{display:none;align-items:center;gap:6px;min-width:0;overflow-x:auto;overflow-y:hidden;scrollbar-width:none}.panels::-webkit-scrollbar{display:none;width:0;height:0}.expanded .panels{display:flex;flex:1}.panel{position:relative;display:inline-flex;align-items:center;gap:7px;min-height:32px;max-width:260px;padding:4px 9px;border:1px solid rgba(148,163,184,.16);border-radius:12px;color:#dbeafe;background:rgba(30,41,59,.72);white-space:nowrap;box-sizing:border-box;cursor:default;transition:background-color .16s ease,border-color .16s ease,box-shadow .16s ease}.panel[data-debug-url]{cursor:pointer}.panel[data-debug-url]:hover,.panel[data-debug-url]:focus,.panel-active{border-color:rgba(56,189,248,.58);background:rgba(14,116,144,.32);outline:0}.panel-active{box-shadow:0 0 0 1px rgba(56,189,248,.18) inset}.panel-title{font-weight:650;color:#f8fafc;overflow:hidden;text-overflow:ellipsis}.metric{display:inline-flex;align-items:center;gap:4px;min-width:0}.metric[data-debug-url]{cursor:pointer}.metric-label{color:#9fb1c7}.metric-icon,.panel-icon{display:inline-block;width:14px;height:14px;background-color:currentColor;-webkit-mask-position:center;mask-position:center;-webkit-mask-size:contain;mask-size:contain;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;flex:none}.panel-icon{color:#7dd3fc;margin-right:2px}.panel-icon--php{width:32px;color:#a3aedb}.metric-value{display:inline-flex;align-items:center;min-height:20px;padding:2px 7px;border-radius:999px;color:#e5edf7;background:rgba(100,116,139,.45);font-weight:700}.metric-active .metric-value{box-shadow:0 0 0 2px rgba(125,211,252,.25)}.badge-success{color:#ecfdf5;background:#047857}.badge-info{color:#eff6ff;background:#2563eb}.badge-warning{color:#171717;background:#f59e0b}.badge-danger{color:#fef2f2;background:#dc2626}.badge-loading{color:#111827;background:#38bdf8}.badge-default{color:#e5edf7;background:rgba(100,116,139,.45)}.ajax-panel{display:none;position:static}.expanded .ajax-panel{display:inline-flex}.ajax-popover{display:none;position:absolute;right:0;bottom:calc(100% + 10px);width:min(680px,calc(100vw - 32px));max-height:360px;overflow:auto;padding:10px;border:1px solid rgba(148,163,184,.22);border-radius:14px;background:rgba(15,23,42,.98);box-shadow:0 20px 60px rgba(2,6,23,.45);box-sizing:border-box}.position-upper .ajax-popover{top:calc(100% + 10px);bottom:auto}.ajax-panel:hover .ajax-popover,.ajax-panel:focus-within .ajax-popover{display:block}.ajax-popover table{width:100%;border-spacing:0;border-collapse:collapse;color:#d6e2f0}.ajax-popover th,.ajax-popover td{padding:6px 8px;border-bottom:1px solid rgba(148,163,184,.16);text-align:left;vertical-align:top}.ajax-popover th{font-size:11px;color:#93a4b8;text-transform:uppercase;letter-spacing:.04em}.ajax-url{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ajax-link{padding:0;border:0;color:#7dd3fc;background:transparent;cursor:pointer}.empty{color:#93a4b8;text-align:center}.controls{display:inline-flex;align-items:center;gap:4px;margin-left:auto}.control{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:11px;color:#dbeafe;background:rgba(51,65,85,.72);font:700 18px/1 ui-sans-serif,system-ui;text-decoration:none;cursor:pointer;box-sizing:border-box}.control:hover,.control:focus{color:#fff;background:rgba(14,165,233,.65);outline:0}.control.disabled{opacity:.45;cursor:default}.drawer{width:calc(100vw - 32px);height:var(--yii-debug-toolbar-drawer-height);border:1px solid rgba(148,163,184,.22);border-top-color:rgba(148,163,184,.12);border-radius:0 0 16px 16px;background:#fff;box-shadow:0 26px 70px rgba(2,6,23,.42);overflow:hidden;box-sizing:border-box}.drawer iframe{display:block;width:100%;height:100%;border:0;background:#fff}.resize-handle{height:6px;margin:0 18px -3px;border-radius:999px;background:linear-gradient(90deg,rgba(56,189,248,.35),rgba(148,163,184,.5),rgba(34,197,94,.35));cursor:ns-resize;position:relative;z-index:1}.position-upper .resize-handle{margin:-3px 18px 0}.legacy-panel{padding:0}.legacy-panel .yii-debug-toolbar-block{display:inline-flex;align-items:center;min-height:32px;padding:0}.legacy-panel a{color:#dbeafe;text-decoration:none}.legacy-panel .yii-debug-toolbar-label{display:inline-flex;margin-left:4px;padding:2px 7px;border-radius:999px;color:#e5edf7;background:rgba(100,116,139,.45);font-weight:700}.error-message{margin-left:8px;color:#fecaca}.loading .brand{opacity:.8}/* Pico-like toolbar action refinements. */.bar{gap:8px;padding:7px;background:linear-gradient(180deg,rgba(15,23,42,.96),rgba(15,23,42,.91));}.brand,.panel,.control{border:1px solid rgba(148,163,184,.22);box-shadow:0 1px 0 rgba(255,255,255,.08) inset,0 10px 26px rgba(2,6,23,.18);transition:transform .16s ease,background-color .16s ease,border-color .16s ease,box-shadow .16s ease,color .16s ease;}.brand{min-height:32px;padding:3px 8px;border-radius:999px;background:linear-gradient(135deg,rgba(14,165,233,.24),rgba(15,23,42,.86) 52%,rgba(34,197,94,.18));}.brand:hover,.brand:focus{border-color:rgba(125,211,252,.58);box-shadow:0 1px 0 rgba(255,255,255,.1) inset,0 14px 32px rgba(14,165,233,.2);outline:0;transform:translateY(-1px);}.brand img,.brand-mark{width:18px;height:18px;}.brand-mark{border-radius:999px;box-shadow:0 0 0 1px rgba(255,255,255,.16) inset;}.opener-icon{width:20px;color:#7dd3fc;font-weight:750;}.panel{gap:6px;min-height:32px;padding:3px 10px;border-radius:999px;background:linear-gradient(180deg,rgba(30,41,59,.88),rgba(15,23,42,.76));}.panel[data-debug-url]:hover,.panel[data-debug-url]:focus,.panel-active{border-color:rgba(56,189,248,.68);background:linear-gradient(180deg,rgba(14,116,144,.42),rgba(15,23,42,.78));box-shadow:0 1px 0 rgba(255,255,255,.1) inset,0 8px 20px rgba(14,165,233,.18);outline:0;}.panel-active{background:linear-gradient(180deg,rgba(14,165,233,.36),rgba(14,116,144,.3));}.panel-title{font-weight:720;letter-spacing:.005em;}.metric{gap:5px;}.metric-label{color:#a8bbd2;font-size:11px;}.metric-value{min-height:18px;padding:0 7px;border:1px solid rgba(255,255,255,.08);box-shadow:0 1px 0 rgba(255,255,255,.1) inset;font-size:11px;line-height:18px;}.metric[data-debug-url]:hover .metric-value,.metric[data-debug-url]:focus .metric-value,.metric-active .metric-value{box-shadow:0 0 0 3px rgba(125,211,252,.18),0 1px 0 rgba(255,255,255,.1) inset;}.badge-success{background:linear-gradient(135deg,#059669,#10b981);color:#052e16;}.badge-info,.badge-loading{background:linear-gradient(135deg,#0284c7,#38bdf8);color:#082f49;}.badge-warning{background:linear-gradient(135deg,#d97706,#fbbf24);color:#1c1917;}.badge-danger{background:linear-gradient(135deg,#dc2626,#fb7185);color:#450a0a;}.badge-default{background:linear-gradient(135deg,rgba(100,116,139,.66),rgba(51,65,85,.72));}.control{width:34px;height:34px;border-radius:999px;border-color:rgba(148,163,184,.22);background:linear-gradient(180deg,rgba(51,65,85,.86),rgba(15,23,42,.78));color:#dbeafe;}.control:hover,.control:focus{border-color:rgba(125,211,252,.64);background:linear-gradient(180deg,rgba(14,165,233,.68),rgba(2,132,199,.55));box-shadow:0 1px 0 rgba(255,255,255,.12) inset,0 8px 20px rgba(14,165,233,.2);outline:0;}.control.disabled,.control.disabled:hover,.control.disabled:focus{border-color:rgba(148,163,184,.18);background:linear-gradient(180deg,rgba(51,65,85,.54),rgba(15,23,42,.62));box-shadow:none;color:#94a3b8;transform:none;}.ajax-popover{border-radius:18px;}:host([data-theme="dark"]){color-scheme:dark;}:host([data-theme="light"]){color:#0f172a;color-scheme:light;}:host([data-theme="light"]) .bar{border-color:rgba(15,23,42,.12);background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(248,250,252,.92));box-shadow:0 20px 60px rgba(15,23,42,.16),0 1px 0 rgba(255,255,255,.85) inset;}:host([data-theme="light"]) .drawer-open .bar{border-bottom-color:rgba(15,23,42,.1);}:host([data-theme="light"]) .brand{border-color:rgba(14,165,233,.22);color:#0f172a;background:linear-gradient(135deg,rgba(14,165,233,.16),rgba(255,255,255,.92) 52%,rgba(34,197,94,.14));box-shadow:0 1px 0 rgba(255,255,255,.86) inset,0 10px 24px rgba(15,23,42,.08);}:host([data-theme="light"]) .brand__link{color:#0f172a}:host([data-theme="light"]) .brand__link:hover,:host([data-theme="light"]) .brand__link:focus{background:rgba(15,23,42,.06);color:#0f172a}:host([data-theme="light"]) .brand-version{border-color:rgba(15,23,42,.12);background:rgba(15,23,42,.05);color:#0f172a}:host([data-theme="light"]) .brand__divider{background:rgba(15,23,42,.18)}:host([data-theme="light"]) .panel-icon{color:#1e293b}:host([data-theme="light"]) .metric-icon{color:#1e293b}:host([data-theme="light"]) .panel-icon--php{color:#4f5b93}:host([data-theme="light"]) .brand:hover,:host([data-theme="light"]) .brand:focus{border-color:rgba(2,132,199,.38);box-shadow:0 1px 0 rgba(255,255,255,.9) inset,0 14px 32px rgba(14,165,233,.16),0 0 0 3px rgba(14,165,233,.1);}:host([data-theme="light"]) .panel{border-color:rgba(15,23,42,.12);color:#0f172a;background:linear-gradient(180deg,rgba(255,255,255,.92),rgba(248,250,252,.84));box-shadow:0 1px 0 rgba(255,255,255,.8) inset,0 10px 24px rgba(15,23,42,.08);}:host([data-theme="light"]) .panel[data-debug-url]:hover,:host([data-theme="light"]) .panel[data-debug-url]:focus,:host([data-theme="light"]) .panel-active{border-color:rgba(2,132,199,.42);background:linear-gradient(180deg,rgba(224,242,254,.96),rgba(255,255,255,.86));box-shadow:0 1px 0 rgba(255,255,255,.86) inset,0 14px 32px rgba(14,165,233,.14),0 0 0 3px rgba(14,165,233,.1);}:host([data-theme="light"]) .panel-active{background:linear-gradient(180deg,rgba(186,230,253,.9),rgba(224,242,254,.78));}:host([data-theme="light"]) .panel-title{color:#0f172a;}:host([data-theme="light"]) .metric-label{color:#64748b;}:host([data-theme="light"]) .metric-value,:host([data-theme="light"]) .legacy-panel .yii-debug-toolbar-label{border-color:rgba(15,23,42,.08);color:#0f172a;background:rgba(226,232,240,.86);box-shadow:0 1px 0 rgba(255,255,255,.82) inset;}:host([data-theme="light"]) .badge-default{background:linear-gradient(135deg,rgba(226,232,240,.96),rgba(203,213,225,.86));color:#0f172a;}:host([data-theme="light"]) .control{border-color:rgba(15,23,42,.12);color:#0f172a;background:linear-gradient(180deg,rgba(255,255,255,.92),rgba(226,232,240,.82));box-shadow:0 1px 0 rgba(255,255,255,.82) inset,0 10px 24px rgba(15,23,42,.08);}:host([data-theme="light"]) .control:hover,:host([data-theme="light"]) .control:focus{border-color:rgba(2,132,199,.42);color:#0369a1;background:linear-gradient(180deg,rgba(224,242,254,.98),rgba(186,230,253,.78));box-shadow:0 1px 0 rgba(255,255,255,.9) inset,0 14px 32px rgba(14,165,233,.14),0 0 0 3px rgba(14,165,233,.1);}:host([data-theme="light"]) .control.disabled,:host([data-theme="light"]) .control.disabled:hover,:host([data-theme="light"]) .control.disabled:focus{border-color:rgba(15,23,42,.1);color:#94a3b8;background:rgba(226,232,240,.62);box-shadow:none;}:host([data-theme="light"]) .ajax-popover{border-color:rgba(15,23,42,.14);background:rgba(255,255,255,.98);box-shadow:0 20px 60px rgba(15,23,42,.16);}:host([data-theme="light"]) .ajax-popover table{color:#0f172a;}:host([data-theme="light"]) .ajax-popover th{color:#64748b;}:host([data-theme="light"]) .ajax-popover th,:host([data-theme="light"]) .ajax-popover td{border-bottom-color:rgba(15,23,42,.1);}:host([data-theme="light"]) .ajax-link{color:#0369a1;}:host([data-theme="light"]) .empty{color:#64748b;}:host([data-theme="light"]) .legacy-panel a{color:#0f172a;}:host([data-theme="light"]) .drawer{border-color:rgba(15,23,42,.14);border-top-color:rgba(15,23,42,.08);background:#fff;box-shadow:0 26px 70px rgba(15,23,42,.18);}:host([data-theme="light"]) .drawer iframe{background:#fff;}@media (max-width:767px){:host{right:8px;bottom:8px}.toolbar{max-width:calc(100vw - 16px)}.expanded .bar,.drawer{width:calc(100vw - 16px)}.expanded .bar{align-items:flex-start;flex-wrap:wrap}.expanded .panels{order:3;flex:0 0 100%;width:100%;padding-top:4px}.panel{max-width:100%}.drawer{height:min(var(--yii-debug-toolbar-drawer-height),70vh)}}@media print{:host{display:none!important}}';

  window.customElements.define(tagName, YiiDebugToolbar);
  trackRequests();
})();
