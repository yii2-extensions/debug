(function () {
  "use strict";

  var on = function (element, event, handler) {
      var i;
      if (null === element) {
        return;
      }
      if (element instanceof NodeList) {
        for (i = 0; i < element.length; i++) {
          element[i].addEventListener(event, handler, false);
        }
        return;
      }
      if (!(element instanceof Array)) {
        element = [element];
      }
      for (i in element) {
        if (typeof element[i].addEventListener !== "function") {
          continue;
        }
        element[i].addEventListener(event, handler, false);
      }
    },
    ajax = function (url, settings) {
      var xhr = new XMLHttpRequest();
      settings = settings || {};
      xhr.open(settings.method || "GET", url, true);
      xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
      xhr.setRequestHeader("Accept", "text/html");
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          if (xhr.status === 200 && settings.success) {
            settings.success(xhr);
          } else if (xhr.status !== 200 && settings.error) {
            settings.error(xhr);
          }
        }
      };
      xhr.send(settings.data || "");
    };

  on(
    document.querySelectorAll(".yii-debug-db-explain-toggle"),
    "click",
    function (e) {
      e.preventDefault();

      var container = this.closest(".yii-debug-db-explain"),
        target = container.querySelector(".yii-debug-db-explain-text"),
        isOpen = container.classList.contains("is-open"),
        self = this;

      if (isOpen) {
        container.classList.remove("is-open");
        self.setAttribute("aria-expanded", "false");
        return;
      }

      // Lazy-load on first open; cached afterwards.
      if (target.dataset.loaded === "1") {
        container.classList.add("is-open");
        self.setAttribute("aria-expanded", "true");
        return;
      }

      container.classList.add("is-loading");
      ajax(this.href, {
        success: function (xhr) {
          target.innerHTML = xhr.responseText;
          target.dataset.loaded = "1";
          container.classList.remove("is-loading");
          container.classList.add("is-open");
          self.setAttribute("aria-expanded", "true");
        },
        error: function () {
          container.classList.remove("is-loading");
        },
      });
    },
  );

  on(
    document.querySelectorAll(".yii-debug-db-explain-all a"),
    "click",
    function () {
      var event = new MouseEvent("click", { cancelable: true, bubbles: true });
      var toggles = document.querySelectorAll(".yii-debug-db-explain-toggle");
      var anyOpen =
        document.querySelectorAll(".yii-debug-db-explain.is-open").length > 0;

      for (var i = 0, len = toggles.length; i < len; i++) {
        var open = toggles[i]
          .closest(".yii-debug-db-explain")
          .classList.contains("is-open");
        // When at least one is open, close every open row; when all are closed,
        // open every row. Skip toggles that are already in the desired state so
        // a half-open list collapses (or expands) to a uniform end state.
        if (anyOpen === open) {
          toggles[i].dispatchEvent(event);
        }
      }

      this.textContent = anyOpen ? "[+] Explain all" : "[-] Explain all";
    },
  );
})();
