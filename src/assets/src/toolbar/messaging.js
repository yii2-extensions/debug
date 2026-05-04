import {
  absoluteUrl,
  getPrimaryToolbar,
  originalFetch,
  originalXhrOpen,
  parseJsonAttribute,
  requestStack,
  requestStackLimit,
  sameUrl,
  toolbars,
} from "./state.js";

/*
 * AJAX request tracking — installs replacements for `XMLHttpRequest.open` and
 * `window.fetch` that record matching debug-eligible requests into the shared
 * `requestStack`, then forwards each to the captured original primitives.
 *
 * Tracked requests skip the toolbar's own data fetch and any URL listed in
 * `data-skip-urls` on the host element. Each completed request gets stamped
 * with the headers Yii's debug middleware adds (`X-Debug-Tag`, `-Duration`,
 * `-Link`) so the toolbar chips can follow the most recent profiled request.
 */

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

  /*
   * Follow the most recent AJAX request that carries an X-Debug-Tag header so
   * the chips reflect what just happened on the server (e.g. login that hit
   * the database) instead of staying frozen on the initial page-load tag.
   */
  for (var i = requestStack.length - 1; i >= 0; i--) {
    var item = requestStack[i];

    if (item.loading || !item.profile) {
      continue;
    }

    toolbars.forEach(function (toolbar) {
      toolbar.followTag(item.profile);
    });

    return;
  }
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

export function trackRequests() {
  if (window.__yiiDebugToolbarTracking) {
    return;
  }

  window.__yiiDebugToolbarTracking = true;
  trackXhr();
  trackFetch();
}
