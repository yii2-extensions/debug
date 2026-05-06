/**
 * Index-page client behavior:
 *   1. Row-click delegation — any click on a `.yii-debug-row-link` <tr>
 *      jumps to that request's view, except when the click started inside an
 *      interactive element (link, button, input).
 *   2. History cursor — peek at requests one by one without leaving the page.
 *      The sidebar's snapshot card mirrors whichever GridView row the cursor
 *      sits on; Prev/Next/Latest/First navigate the cursor; rows carry the
 *      payload as `data-yii-debug-*` attrs so we don't need an extra JSON blob.
 */

(function () {
  document.addEventListener("click", function (event) {
    var row = event.target.closest(".yii-debug-row-link");
    if (!row) {
      return;
    }
    if (event.target.closest("a, button, input, select, textarea, label")) {
      return;
    }
    var href = row.getAttribute("data-href");
    if (!href) {
      return;
    }
    if (event.metaKey || event.ctrlKey || event.button === 1) {
      window.open(href, "_blank", "noopener");
      return;
    }
    window.location.href = href;
  });
})();

(function () {
  var section = document.querySelector("[data-yii-debug-history-cursor]");
  if (!section) {
    return;
  }

  var rows = Array.prototype.slice.call(
    document.querySelectorAll("tr[data-yii-debug-tag]"),
  );
  if (rows.length === 0) {
    return;
  }

  var STATUS_VARIANTS = ["success", "warning", "danger", "muted"];

  /**
   * Honour `data-yii-debug-cursor-init` so the cursor lands on the tag the
   * user was inspecting when they clicked "History" from a panel view. Falls
   * back to row 0 (latest capture) when the attribute is missing or the tag
   * isn't on this page.
   */
  var initTag = section.getAttribute("data-yii-debug-cursor-init") || "";
  var cursor = 0;
  if (initTag !== "") {
    for (var ri = 0; ri < rows.length; ri++) {
      if (rows[ri].getAttribute("data-yii-debug-tag") === initTag) {
        cursor = ri;
        break;
      }
    }
  }

  /**
   * Strip scheme/host/port from a captured URL so the snapshot card shows
   * just the request path — matches the CURRENT REQUEST card and saves
   * horizontal space on long URLs (issue #23). Console invocations
   * (`php yii ...`) and unparsable values are returned verbatim.
   */
  function urlToPath(url) {
    if (!url || url.indexOf("php yii ") === 0) {
      return url;
    }
    try {
      var parsed = new URL(url);
      return (parsed.pathname || "/") + parsed.search + parsed.hash;
    } catch (_e) {
      return url;
    }
  }

  function snapshotFromRow(row) {
    var status = parseInt(row.getAttribute("data-yii-debug-status") || "0", 10);
    var rawUrl = row.getAttribute("data-yii-debug-url") || "";
    return {
      tag: row.getAttribute("data-yii-debug-tag") || "",
      method: row.getAttribute("data-yii-debug-method") || "",
      url: urlToPath(rawUrl),
      fullUrl: rawUrl,
      status: status,
      time: row.getAttribute("data-yii-debug-time") || "",
      ajax: row.getAttribute("data-yii-debug-ajax") === "1",
    };
  }

  function statusVariant(status) {
    if (status >= 500) return "danger";
    if (status >= 400) return "warning";
    if (status >= 300) return "muted";
    if (status >= 200) return "success";
    return "muted";
  }

  function update() {
    rows.forEach(function (r, i) {
      r.classList.toggle("is-cursor", i === cursor);
    });

    var snap = snapshotFromRow(rows[cursor]);

    section.querySelectorAll("[data-snapshot-field]").forEach(function (el) {
      var field = el.getAttribute("data-snapshot-field");
      if (field === "method") {
        el.textContent = snap.method;
      } else if (field === "url") {
        el.textContent = snap.url;
        if (snap.fullUrl) {
          el.setAttribute("title", snap.fullUrl);
        }
      } else if (field === "status") {
        el.textContent = snap.status ? String(snap.status) : "–";
        STATUS_VARIANTS.forEach(function (v) {
          el.classList.remove("yii-debug-snapshot-status-" + v);
        });
        el.classList.add(
          "yii-debug-snapshot-status-" + statusVariant(snap.status),
        );
      } else if (field === "time") {
        el.textContent = snap.time;
        el.hidden = snap.time === "";
      } else if (field === "ajax") {
        el.hidden = !snap.ajax;
      }
    });

    var card = section.querySelector(".yii-debug-history-card");
    if (card) {
      card.setAttribute(
        "title",
        (snap.method + " " + (snap.fullUrl || snap.url)).trim(),
      );
    }

    var firstBtn = section.querySelector('[data-yii-debug-cursor="first"]');
    var prevBtn = section.querySelector('[data-yii-debug-cursor="prev"]');
    var nextBtn = section.querySelector('[data-yii-debug-cursor="next"]');
    var latestBtn = section.querySelector('[data-yii-debug-cursor="latest"]');
    var atTop = cursor === 0;
    var atBottom = cursor === rows.length - 1;
    if (firstBtn) firstBtn.classList.toggle("is-disabled", atTop);
    if (prevBtn) prevBtn.classList.toggle("is-disabled", atTop);
    if (nextBtn) nextBtn.classList.toggle("is-disabled", atBottom);
    if (latestBtn) latestBtn.classList.toggle("is-disabled", atBottom);
  }

  function ensureVerticallyVisible(row) {
    /**
     * Avoid `scrollIntoView` — it also scrolls inline (horizontally), and
     * the GridView is usually wider than the viewport, so it would yank
     * the page sideways. We only need vertical visibility.
     */
    var rect = row.getBoundingClientRect();
    var viewportHeight =
      window.innerHeight || document.documentElement.clientHeight;
    if (rect.top >= 0 && rect.bottom <= viewportHeight) {
      return;
    }
    var target =
      window.scrollY + rect.top - viewportHeight / 2 + rect.height / 2;
    window.scrollTo({ top: Math.max(0, target), behavior: "smooth" });
  }

  function moveTo(index) {
    if (index < 0 || index >= rows.length || index === cursor) {
      return;
    }
    cursor = index;
    update();
    ensureVerticallyVisible(rows[cursor]);
  }

  section.addEventListener("click", function (event) {
    var btn = event.target.closest("[data-yii-debug-cursor]");
    if (btn && section.contains(btn)) {
      event.preventDefault();
      if (btn.classList.contains("is-disabled")) {
        return;
      }
      var dir = btn.getAttribute("data-yii-debug-cursor");
      if (dir === "prev") moveTo(cursor - 1);
      else if (dir === "next") moveTo(cursor + 1);
      else if (dir === "first") moveTo(0);
      else if (dir === "latest") moveTo(rows.length - 1);
    }
  });

  update();
  if (cursor !== 0) {
    ensureVerticallyVisible(rows[cursor]);
  }
})();
