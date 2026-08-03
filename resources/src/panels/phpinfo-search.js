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
  directiveCount,
  directiveModuleCount,
  titleMatchCount,
) {
  var parts = [];

  if (titleMatchCount > 0) {
    parts.push(plural(titleMatchCount, "module", "modules"));
  }

  if (directiveCount > 0) {
    parts.push(
      plural(directiveCount, "directive", "directives") +
        " in " +
        plural(directiveModuleCount, "module", "modules"),
    );
  }

  return parts.join(" · ");
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

function resetSection(section) {
  toArray(section.querySelectorAll(".yii-debug-table-wrap")).forEach(
    function (wrap) {
      wrap.hidden = false;
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
    var directiveCount = 0;
    var directiveModuleCount = 0;
    var titleMatchCount = 0;

    sections.forEach(function (section) {
      resetSection(section);

      var title = normalizePhpInfoQuery(section.getAttribute("data-section"));
      var titleMatch = title.indexOf(query) !== -1;
      var sectionDirectiveCount = 0;

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
            sectionDirectiveCount += matchingRows.length;

            rows.forEach(function (row) {
              var rowMatch = matchingRows.indexOf(row) !== -1;
              var header = row.classList.contains("h");

              row.hidden = !rowMatch && !(header && matchingRows.length > 0);
              row.classList.toggle("is-search-match", rowMatch);
            });
          },
        );
      }

      var visible = titleMatch || sectionDirectiveCount > 0;

      section.hidden = !visible;

      if (visible) visibleIds[section.id] = true;

      if (sectionDirectiveCount > 0) {
        directiveCount += sectionDirectiveCount;
        directiveModuleCount++;
      }
    });

    updateToc("", visibleIds);

    if (status) {
      status.textContent = formatPhpInfoSearchStatus(
        directiveCount,
        directiveModuleCount,
        titleMatchCount,
      );
    }

    if (empty) {
      empty.hidden = directiveCount + titleMatchCount !== 0;
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
