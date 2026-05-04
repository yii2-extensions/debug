/**
 * PhpInfo panel client behavior:
 *   - Search: filters whole modules by title or directive content.
 *   - TOC sync: IntersectionObserver keeps the entry of the section currently
 *     in view highlighted. Click on a TOC entry smooth-scrolls and updates the
 *     URL hash so the dev can copy/paste a deep link to a specific module.
 */

(function () {
    var search = document.querySelector('[data-yii-debug-phpinfo-search]');
    var empty = document.querySelector('[data-yii-debug-phpinfo-empty]');
    var sections = Array.prototype.slice.call(
        document.querySelectorAll('.yii-debug-phpinfo-section'),
    );
    var tocLinks = Array.prototype.slice.call(
        document.querySelectorAll('.yii-debug-phpinfo-toc-link'),
    );

    if (!sections.length) {
        return;
    }

    function setHidden(el, hide) {
        if (!el) return;
        el.hidden = hide;
        if (el.parentElement && el.parentElement.tagName === 'LI') {
            el.parentElement.hidden = hide;
        }
    }

    function applyFilter() {
        var query = (search ? search.value : '').trim().toLowerCase();

        if (!query) {
            sections.forEach(function (s) { s.hidden = false; });
            tocLinks.forEach(function (l) { setHidden(l, false); });
            if (empty) empty.hidden = true;
            return;
        }

        var matches = 0;
        var visible = Object.create(null);
        var firstTitleHit = null;
        var firstContentHit = null;

        sections.forEach(function (section) {
            var title = (section.getAttribute('data-section') || '').toLowerCase();
            var content = section.textContent.toLowerCase();
            var titleMatch = title.indexOf(query) !== -1;
            var hit = titleMatch || content.indexOf(query) !== -1;
            section.hidden = !hit;
            if (hit) {
                visible[section.id] = true;
                matches++;
                if (titleMatch && !firstTitleHit) firstTitleHit = section;
                else if (!firstContentHit) firstContentHit = section;
            }
        });

        /**
         * Prefer scroll-targeting a section whose title matches — content
         * matches catch incidental hits (Overview's Configure Command lists
         * every `--with-X` flag, so any extension name appears there) which
         * would otherwise always scroll the dev to Overview first.
         */
        var firstVisible = firstTitleHit || firstContentHit;

        tocLinks.forEach(function (link) {
            var target = link.getAttribute('data-toc-target') || '';
            setHidden(link, !visible[target]);
        });

        if (empty) empty.hidden = matches !== 0;

        if (firstVisible) {
            firstVisible.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (search) {
        search.addEventListener('input', applyFilter);
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var id = entry.target.id;
                tocLinks.forEach(function (link) {
                    link.classList.toggle('is-active', link.getAttribute('data-toc-target') === id);
                });
            });
        }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });

        sections.forEach(function (section) { observer.observe(section); });
    }

    tocLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var hash = link.getAttribute('href') || '';
            var id = hash.charAt(0) === '#' ? hash.slice(1) : hash;
            var target = document.getElementById(id);
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (history.replaceState) {
                history.replaceState(null, '', '#' + id);
            }
        });
    });
}());
