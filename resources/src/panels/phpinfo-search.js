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

export function matchesPhpInfoCompactModule(title, content, query) {
  return (
    normalizePhpInfoQuery(title).indexOf(query) !== -1 ||
    normalizePhpInfoQuery(content).indexOf(query) !== -1
  );
}

function plural(count, singular, pluralForm) {
  return count + " " + (count === 1 ? singular : pluralForm);
}

/**
 * Names each kind of hit separately. Reusing "module" for a matching section
 * name, a matching extension and the section a matching row lives in made the
 * summary ambiguous, so every count now carries its own noun.
 */
export function formatPhpInfoSearchStatus(
  rowMatchCount,
  moduleMatchCount,
  extensionMatchCount,
) {
  var parts = [];

  if (moduleMatchCount > 0) {
    parts.push(plural(moduleMatchCount, "module", "modules"));
  }

  if (extensionMatchCount > 0) {
    parts.push(plural(extensionMatchCount, "extension", "extensions"));
  }

  if (rowMatchCount > 0) {
    parts.push(plural(rowMatchCount, "matching row", "matching rows"));
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
    toArray(
      section.querySelectorAll(
        ".yii-debug-phpinfo-table-section.is-directives tr",
      ),
    ).forEach(function (row) {
      if (row.classList.contains("h")) return;

      var label = row.querySelector('th[scope="row"]');
      var values = toArray(row.querySelectorAll("td"));

      if (
        label !== null &&
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
  var compactModules = toArray(
    root.querySelectorAll("[data-yii-debug-phpinfo-compact-module]"),
  );
  var compactGroups = toArray(
    root.querySelectorAll(".yii-debug-phpinfo-extension-group"),
  );
  var compactDetails = root.querySelector(
    "[data-yii-debug-phpinfo-extensions]",
  );
  var overviewStaticSections = toArray(
    root.querySelectorAll(
      "#phpinfo-overview .yii-debug-phpinfo-overview-hero-section:not(.yii-debug-phpinfo-extensions)",
    ),
  );
  var configureDetails = root.querySelector(
    "#phpinfo-overview > .yii-debug-disclosure",
  );
  var tocLinks = toArray(root.querySelectorAll(".yii-debug-phpinfo-toc-link"));
  var tocGroups = toArray(
    root.querySelectorAll("[data-yii-debug-phpinfo-toc-group]"),
  );
  var sectionById = Object.create(null);
  var compactById = Object.create(null);
  var compactDetailsOpenBeforeFilter = false;
  var filterActive = false;
  var filterTimer;
  var selectedId;

  if (!sections.length) return;

  sections.forEach(function (section) {
    if (section.id !== "") {
      sectionById[section.id] = section;
    }
  });

  compactModules.forEach(function (module) {
    compactById[module.id] = module;
  });

  function resetCompactModules() {
    compactModules.forEach(function (module) {
      module.hidden = false;
      module.classList.remove("is-search-match");
    });

    compactGroups.forEach(function (group) {
      group.hidden = false;

      var count = group.querySelector(
        "[data-yii-debug-phpinfo-extension-group-count]",
      );

      if (count) {
        count.textContent = count.getAttribute("data-yii-debug-phpinfo-total");
      }
    });

    overviewStaticSections.forEach(function (section) {
      section.hidden = false;
    });

    if (configureDetails) configureDetails.hidden = false;
  }

  function beginFilter() {
    if (filterActive) return;

    compactDetailsOpenBeforeFilter = compactDetails
      ? compactDetails.open
      : false;
    filterActive = true;
  }

  function endFilter() {
    if (!filterActive) return;

    if (compactDetails) {
      compactDetails.open = compactDetailsOpenBeforeFilter;
    }

    filterActive = false;
  }

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
    resetCompactModules();

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
    endFilter();
    selectedId = sectionById[id] ? id : sections[0].id;

    if (search) search.value = "";

    showSelected();

    if (updateHash) replaceHash(selectedId);
  }

  function applyFilter() {
    var query = normalizePhpInfoQuery(search ? search.value : "");

    if (!query) {
      endFilter();
      showSelected();
      return;
    }

    beginFilter();

    var visibleIds = Object.create(null);
    var matchCount = 0;
    var titleMatchCount = 0;
    var compactMatchCount = 0;

    resetCompactModules();

    sections.forEach(function (section) {
      resetSection(section);

      var title = normalizePhpInfoQuery(section.getAttribute("data-section"));
      var titleMatch = title.indexOf(query) !== -1;
      var sectionMatchCount = 0;
      var compactSectionMatchCount = 0;

      if (titleMatch) {
        titleMatchCount++;
      } else if (section.id === "phpinfo-overview") {
        compactModules.forEach(function (module) {
          var moduleMatch = matchesPhpInfoCompactModule(
            module.getAttribute("data-section"),
            module.textContent,
            query,
          );

          module.hidden = !moduleMatch;
          module.classList.toggle("is-search-match", moduleMatch);

          if (moduleMatch) compactSectionMatchCount++;
        });

        compactGroups.forEach(function (group) {
          var visibleModules = toArray(
            group.querySelectorAll("[data-yii-debug-phpinfo-compact-module]"),
          ).filter(function (module) {
            return !module.hidden;
          });
          var groupCount = group.querySelector(
            "[data-yii-debug-phpinfo-extension-group-count]",
          );

          group.hidden = visibleModules.length === 0;

          if (groupCount && visibleModules.length > 0) {
            groupCount.textContent = formatPhpInfoFilteredCount(
              visibleModules.length,
              groupCount.getAttribute("data-yii-debug-phpinfo-total"),
            );
          }
        });

        if (compactDetails) {
          compactDetails.open = compactSectionMatchCount > 0;
        }

        overviewStaticSections.forEach(function (overviewSection) {
          overviewSection.hidden = true;
        });

        if (configureDetails) configureDetails.hidden = true;
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

            var matchingRowSet = new Set(matchingRows);

            rows.forEach(function (row) {
              var rowMatch = matchingRowSet.has(row);
              var header = row.classList.contains("h");

              row.hidden = !rowMatch && !(header && matchingRows.length > 0);
              row.classList.toggle("is-search-match", rowMatch);
            });
          },
        );
      }

      var visible =
        titleMatch || sectionMatchCount > 0 || compactSectionMatchCount > 0;

      section.hidden = !visible;

      if (visible) visibleIds[section.id] = true;

      matchCount += sectionMatchCount;
      compactMatchCount += compactSectionMatchCount;
    });

    updateToc("", visibleIds);

    if (status) {
      status.textContent = formatPhpInfoSearchStatus(
        matchCount,
        titleMatchCount,
        compactMatchCount,
      );
    }

    if (empty) {
      empty.hidden = matchCount + titleMatchCount + compactMatchCount !== 0;
    }

    if (clear) clear.hidden = false;
  }

  function clearSearch() {
    window.clearTimeout(filterTimer);

    if (search) {
      search.value = "";
      search.focus();
    }

    endFilter();
    showSelected();
  }

  function revealCompactModule(module, scroll) {
    selectSection("phpinfo-overview", false);

    if (compactDetails) compactDetails.open = true;

    if (scroll && module && typeof module.scrollIntoView === "function") {
      window.setTimeout(function () {
        module.scrollIntoView({ behavior: "smooth", block: "center" });
      }, 0);
    }
  }

  markLocalOverrides(sections);

  var hashId = window.location.hash.replace(/^#/, "");

  var hashCompactModule = compactById[hashId] || null;

  selectedId = sectionById[hashId]
    ? hashId
    : hashCompactModule
      ? "phpinfo-overview"
      : sections[0].id;
  showSelected();

  if (hashCompactModule) revealCompactModule(hashCompactModule, true);

  if (search) {
    search.addEventListener("input", function () {
      window.clearTimeout(filterTimer);
      filterTimer = window.setTimeout(applyFilter, 120);
    });
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

    if (sectionById[id]) {
      selectSection(id, false);
    } else if (compactById[id]) {
      revealCompactModule(compactById[id], true);
    }
  });
}

if (typeof document !== "undefined") {
  initPhpInfoSearch(document);
}
