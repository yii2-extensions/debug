/**
 * PHP Info navigation and search.
 *
 * The TOC behaves as a module selector: only Overview or the selected module
 * is visible during normal browsing. Search temporarily switches to a result
 * view where module-name matches show a complete module and directive matches
 * show only the matching rows plus their table headers.
 */

function toArray(value) {
  return Array.prototype.slice.call(value);
}

export function normalizePhpInfoQuery(value) {
  return String(value || "")
    .trim()
    .toLowerCase();
}

function plural(count, singular, pluralForm) {
  return count + " " + (count === 1 ? singular : pluralForm);
}

export function formatPhpInfoSearchStatus(
  matchCount,
  matchModuleCount,
  titleMatchCount,
) {
  var parts = [];

  if (titleMatchCount > 0) {
    parts.push(plural(titleMatchCount, "module", "modules"));
  }

  if (matchCount > 0) {
    parts.push(
      plural(matchCount, "match", "matches") +
        " in " +
        plural(matchModuleCount, "module", "modules"),
    );
  }

  return parts.join(" · ");
}

export function formatPhpInfoFilteredCount(matchCount, totalLabel) {
  return matchCount + " of " + totalLabel;
}

export function resolvePhpInfoTocGroupState(targets, activeId, visibleIds) {
  var visibleTargets =
    visibleIds === null
      ? targets
      : targets.filter(function (target) {
          return Boolean(visibleIds[target]);
        });

  return {
    hidden: visibleTargets.length === 0,
    open:
      visibleIds === null
        ? targets.indexOf(activeId) !== -1
        : visibleTargets.length > 0,
  };
}

function setLinkHidden(link, hidden) {
  link.hidden = hidden;

  if (link.parentElement && link.parentElement.tagName === "LI") {
    link.parentElement.hidden = hidden;
  }
}

function resetTableSection(wrap) {
  wrap.hidden = false;

  var count = wrap.querySelector(".yii-debug-phpinfo-table-section-count");

  if (count) {
    var original = count.getAttribute("data-yii-debug-phpinfo-total");

    if (original === null) {
      original = count.textContent.trim();
      count.setAttribute("data-yii-debug-phpinfo-total", original);
    }

    count.textContent = original;
  }

  if (wrap.hasAttribute("data-yii-debug-phpinfo-collapsible")) {
    wrap.open =
      wrap.getAttribute("data-yii-debug-phpinfo-default-open") === "true";
  }
}

function showFilteredTableCount(wrap, matchCount) {
  var count = wrap.querySelector(".yii-debug-phpinfo-table-section-count");

  if (!count) return;

  var total = count.getAttribute("data-yii-debug-phpinfo-total");

  if (total === null) {
    total = count.textContent.trim();
    count.setAttribute("data-yii-debug-phpinfo-total", total);
  }

  count.textContent = formatPhpInfoFilteredCount(matchCount, total);
}

function resetSection(section) {
  toArray(section.querySelectorAll(".yii-debug-table-wrap")).forEach(
    function (wrap) {
      resetTableSection(wrap);
    },
  );

  toArray(section.querySelectorAll("tr")).forEach(function (row) {
    row.hidden = false;
    row.classList.remove("is-search-match");
  });
}

function markLocalOverrides(sections) {
  sections.forEach(function (section) {
    toArray(section.querySelectorAll("tr")).forEach(function (row) {
      if (row.classList.contains("h")) return;

      var values = toArray(row.querySelectorAll("td"));

      if (
        values.length === 2 &&
        values[0].textContent.trim() !== values[1].textContent.trim()
      ) {
        row.classList.add("has-local-override");
        values[0].title = "Local value differs from master value";
      }
    });
  });
}

export function initPhpInfoSearch(root) {
  var search = root.querySelector("[data-yii-debug-phpinfo-search]");
  var clear = root.querySelector("[data-yii-debug-phpinfo-clear]");
  var status = root.querySelector("[data-yii-debug-phpinfo-status]");
  var empty = root.querySelector("[data-yii-debug-phpinfo-empty]");
  var sections = toArray(root.querySelectorAll(".yii-debug-phpinfo-section"));
  var tocLinks = toArray(root.querySelectorAll(".yii-debug-phpinfo-toc-link"));
  var tocGroups = toArray(
    root.querySelectorAll("[data-yii-debug-phpinfo-toc-group]"),
  );
  var sectionById = Object.create(null);
  var selectedId;

  if (!sections.length) return;

  sections.forEach(function (section) {
    sectionById[section.id] = section;
  });

  function updateToc(activeId, visibleIds) {
    tocLinks.forEach(function (link) {
      var target = link.getAttribute("data-toc-target") || "";
      var active = target === activeId;

      link.classList.toggle("is-active", active);

      if (active) {
        link.setAttribute("aria-current", "page");
      } else {
        link.removeAttribute("aria-current");
      }

      setLinkHidden(link, visibleIds !== null && !visibleIds[target]);
    });

    tocGroups.forEach(function (group) {
      var targets = toArray(
        group.querySelectorAll(".yii-debug-phpinfo-toc-link"),
      ).map(function (link) {
        return link.getAttribute("data-toc-target") || "";
      });
      var state = resolvePhpInfoTocGroupState(targets, activeId, visibleIds);

      group.hidden = state.hidden;
      group.open = state.open;
    });
  }

  function showSelected() {
    sections.forEach(function (section) {
      resetSection(section);
      section.hidden = section.id !== selectedId;
    });

    updateToc(selectedId, null);

    if (status) status.textContent = "";
    if (empty) empty.hidden = true;
    if (clear) clear.hidden = true;
  }

  function replaceHash(id) {
    if (!window.history || !window.history.replaceState) return;

    window.history.replaceState(null, "", "#" + id);
  }

  function selectSection(id, updateHash) {
    selectedId = sectionById[id] ? id : sections[0].id;

    if (search) search.value = "";

    showSelected();

    if (updateHash) replaceHash(selectedId);
  }

  function applyFilter() {
    var query = normalizePhpInfoQuery(search ? search.value : "");

    if (!query) {
      showSelected();
      return;
    }

    var visibleIds = Object.create(null);
    var matchCount = 0;
    var matchModuleCount = 0;
    var titleMatchCount = 0;

    sections.forEach(function (section) {
      resetSection(section);

      var title = normalizePhpInfoQuery(section.getAttribute("data-section"));
      var titleMatch = title.indexOf(query) !== -1;
      var sectionMatchCount = 0;

      if (titleMatch) {
        titleMatchCount++;
      } else if (section.classList.contains("yii-debug-phpinfo-module")) {
        toArray(section.querySelectorAll(".yii-debug-table-wrap")).forEach(
          function (wrap) {
            var rows = toArray(wrap.querySelectorAll("tr"));
            var matchingRows = rows.filter(function (row) {
              var key = row.querySelector('th[scope="row"], th.e, td.e');

              return (
                key !== null &&
                normalizePhpInfoQuery(key.textContent).indexOf(query) !== -1
              );
            });

            wrap.hidden = matchingRows.length === 0;
            sectionMatchCount += matchingRows.length;

            showFilteredTableCount(wrap, matchingRows.length);

            if (wrap.hasAttribute("data-yii-debug-phpinfo-collapsible")) {
              wrap.open = matchingRows.length > 0;
            }

            rows.forEach(function (row) {
              var rowMatch = matchingRows.indexOf(row) !== -1;
              var header = row.classList.contains("h");

              row.hidden = !rowMatch && !(header && matchingRows.length > 0);
              row.classList.toggle("is-search-match", rowMatch);
            });
          },
        );
      }

      var visible = titleMatch || sectionMatchCount > 0;

      section.hidden = !visible;

      if (visible) visibleIds[section.id] = true;

      if (sectionMatchCount > 0) {
        matchCount += sectionMatchCount;
        matchModuleCount++;
      }
    });

    updateToc("", visibleIds);

    if (status) {
      status.textContent = formatPhpInfoSearchStatus(
        matchCount,
        matchModuleCount,
        titleMatchCount,
      );
    }

    if (empty) {
      empty.hidden = matchCount + titleMatchCount !== 0;
    }

    if (clear) clear.hidden = false;
  }

  function clearSearch() {
    if (search) {
      search.value = "";
      search.focus();
    }

    showSelected();
  }

  markLocalOverrides(sections);

  var hashId = window.location.hash.replace(/^#/, "");

  selectedId = sectionById[hashId] ? hashId : sections[0].id;
  showSelected();

  if (search) {
    search.addEventListener("input", applyFilter);
    search.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && search.value !== "") {
        event.preventDefault();
        clearSearch();
      }
    });
  }

  if (clear) {
    clear.addEventListener("click", clearSearch);
  }

  tocLinks.forEach(function (link) {
    link.addEventListener("click", function (event) {
      var id = link.getAttribute("data-toc-target") || "";

      if (!sectionById[id]) return;

      event.preventDefault();
      selectSection(id, true);
    });
  });

  window.addEventListener("hashchange", function () {
    var id = window.location.hash.replace(/^#/, "");

    if (sectionById[id]) selectSection(id, false);
  });
}

if (typeof document !== "undefined") {
  initPhpInfoSearch(document);
}
