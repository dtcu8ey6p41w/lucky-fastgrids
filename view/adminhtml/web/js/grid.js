/**
 * Lucky_FastGrids — gedeelde grid-kern. Kale JavaScript, geen RequireJS/Knockout,
 * geen inline scripts. Rijen komen als JSON binnen en worden met de DOM-API gerenderd.
 *
 * Gebruik: window.LuckyFastGrid.init({ rootId, storageKey, defaultSort, rowUrl, rowTitle,
 *   rowClickExclude, cellFor, summary }) — zie orders.js / customers.js.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    function init(opts) {
        var root = document.getElementById(opts.rootId);
        if (!root) { return; }

        var cfg = JSON.parse(root.getAttribute('data-config'));
        var L = cfg.labels || {};
        var form = root.querySelector('#fg-filters');
        var body = root.querySelector('#fg-body');
        var limitSel = root.querySelector('#fg-limit');
        var headerRow = root.querySelector('thead tr');
        var headers = Array.prototype.slice.call(root.querySelectorAll('th[data-col]'));
        var defaultOrder = headers.map(function (th) { return th.getAttribute('data-col'); });
        var descByDefault = cfg.descByDefault || [];
        var colTypes = cfg.colTypes || {};
        var LONG_TYPES = ['address', 'text'];

        var state = {
            page: 1,
            limit: cfg.defaultLimit,
            sort: opts.defaultSort,
            dir: 'desc',
            filters: {},
            hiddenCols: {},
            colOrder: defaultOrder.slice()
        };
        var requestSeq = 0;

        /* ---------- hulpfuncties voor adapters ---------- */
        function link(href, text, className) {
            var a = document.createElement('a');
            a.href = href;
            a.textContent = text;
            if (className) { a.className = className; }
            return a;
        }
        function badge(text, className, title) {
            var s = document.createElement('span');
            s.className = 'fg-status ' + (className || '');
            s.textContent = text;
            if (title) { s.title = title; }
            return s;
        }
        var api = {
            cfg: cfg,
            labels: L,
            link: link,
            badge: badge,
            setFilters: function (f) {
                state.filters = f;
                state.page = 1;
                writeFilters(f);
                load();
                window.scrollTo(0, 0);
            }
        };

        /* ---------- kolomvolgorde ---------- */
        function currentKeys() {
            return Array.prototype.map.call(headerRow.children, function (th) { return th.getAttribute('data-col'); });
        }
        function applyColumnOrder() {
            var order = state.colOrder.filter(function (k) { return defaultOrder.indexOf(k) !== -1; });
            defaultOrder.forEach(function (k) { if (order.indexOf(k) === -1) { order.push(k); } });
            state.colOrder = order;
            var byKey = {};
            headers.forEach(function (th) { byKey[th.getAttribute('data-col')] = th; });
            order.forEach(function (k) { headerRow.appendChild(byKey[k]); });
            Array.prototype.forEach.call(body.rows, function (tr) {
                var cells = {};
                Array.prototype.forEach.call(tr.cells, function (td) { cells[td.getAttribute('data-col')] = td; });
                order.forEach(function (k) { if (cells[k]) { tr.appendChild(cells[k]); } });
            });
        }

        /* ---------- state bewaren/herstellen ---------- */
        function loadState() {
            try {
                var saved = JSON.parse(localStorage.getItem(opts.storageKey) || 'null');
                if (saved && typeof saved === 'object') {
                    state.limit = saved.limit || state.limit;
                    state.sort = saved.sort || state.sort;
                    state.dir = saved.dir || state.dir;
                    state.filters = saved.filters || {};
                    state.hiddenCols = saved.hiddenCols || {};
                    if (Array.isArray(saved.colOrder) && saved.colOrder.length) { state.colOrder = saved.colOrder; }
                }
            } catch (e) { /* localStorage niet beschikbaar: prima */ }
            // #q=... in de URL overschrijft het zoekveld (bv. link vanaf een andere pagina)
            var hash = (location.hash || '').replace(/^#/, '');
            if (hash) {
                var p = new URLSearchParams(hash);
                if (p.has('q')) { state.filters = { q: p.get('q') }; }
            }
        }
        function saveState() {
            try {
                localStorage.setItem(opts.storageKey, JSON.stringify({
                    limit: state.limit, sort: state.sort, dir: state.dir,
                    filters: state.filters, hiddenCols: state.hiddenCols, colOrder: state.colOrder
                }));
            } catch (e) { /* negeren */ }
        }

        /* ---------- filters <-> formulier ---------- */
        function readFilters() {
            var f = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name) { return; }
                if (el.type === 'checkbox') {
                    if (el.checked) { (f[el.name] = f[el.name] || []).push(el.value); }
                } else if (el.value !== '') {
                    f[el.name] = el.value;
                }
            });
            return f;
        }
        function writeFilters(f) {
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name) { return; }
                if (el.type === 'checkbox') {
                    el.checked = (f[el.name] || []).indexOf(el.value) !== -1;
                } else {
                    el.value = f[el.name] || '';
                }
            });
            updateDropdownLabels();
        }
        function updateDropdownLabels() {
            form.querySelectorAll('details.fg-dropdown').forEach(function (d) {
                var summary = d.querySelector('summary');
                var checked = Array.prototype.filter.call(d.querySelectorAll('input[type="checkbox"]'), function (c) { return c.checked; });
                if (!checked.length) {
                    summary.textContent = summary.getAttribute('data-default');
                } else if (checked.length <= 2) {
                    summary.textContent = checked.map(function (c) { return c.parentNode.textContent.trim(); }).join(', ');
                } else {
                    summary.textContent = checked.length + ' ' + (L.chosen || 'gekozen');
                }
            });
        }
        document.addEventListener('click', function (e) {
            root.querySelectorAll('details.fg-dropdown[open]').forEach(function (d) {
                if (!d.contains(e.target)) { d.removeAttribute('open'); }
            });
        });

        /* ---------- kolommen verbergen ---------- */
        function applyColumns() {
            headers.forEach(function (th) { th.hidden = !!state.hiddenCols[th.getAttribute('data-col')]; });
            root.querySelectorAll('input[data-col-toggle]').forEach(function (cb) {
                cb.checked = !state.hiddenCols[cb.getAttribute('data-col-toggle')];
            });
            Array.prototype.forEach.call(body.rows, function (tr) {
                Array.prototype.forEach.call(tr.cells, function (td) { td.hidden = !!state.hiddenCols[td.getAttribute('data-col')]; });
            });
        }

        /* ---------- kolommen verslepen ---------- */
        var dragKey = null;
        function clearDropMarks() {
            headers.forEach(function (th) { th.classList.remove('fg-drop-left', 'fg-drop-right'); });
        }
        headers.forEach(function (th) {
            th.addEventListener('dragstart', function (e) {
                dragKey = th.getAttribute('data-col');
                th.classList.add('fg-dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', dragKey); } catch (err) { /* oude browsers */ }
            });
            th.addEventListener('dragend', function () {
                dragKey = null;
                th.classList.remove('fg-dragging');
                clearDropMarks();
            });
            th.addEventListener('dragover', function (e) {
                if (!dragKey || dragKey === th.getAttribute('data-col')) { return; }
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                var rect = th.getBoundingClientRect();
                clearDropMarks();
                th.classList.add((e.clientX - rect.left) < rect.width / 2 ? 'fg-drop-left' : 'fg-drop-right');
            });
            th.addEventListener('dragleave', function () { th.classList.remove('fg-drop-left', 'fg-drop-right'); });
            th.addEventListener('drop', function (e) {
                if (!dragKey) { return; }
                e.preventDefault();
                var targetKey = th.getAttribute('data-col');
                if (targetKey === dragKey) { return; }
                var rect = th.getBoundingClientRect();
                var before = (e.clientX - rect.left) < rect.width / 2;
                var order = currentKeys().filter(function (k) { return k !== dragKey; });
                order.splice(order.indexOf(targetKey) + (before ? 0 : 1), 0, dragKey);
                state.colOrder = order;
                applyColumnOrder();
                clearDropMarks();
                saveState();
            });
        });

        /* ---------- renderen ---------- */
        function cellFor(key, item) {
            var td = document.createElement('td');
            td.setAttribute('data-col', key);
            td.hidden = !!state.hiddenCols[key];
            var v = item[key];
            if (opts.cellFor && opts.cellFor(td, key, v, item, api)) {
                return td;
            }
            var type = colTypes[key] || 'text';
            if (type === 'plugin') {
                // Door een plugin berekend: {text} | {html} | {links:[{href,label}]}
                if (v && typeof v === 'object') {
                    if (v.links) {
                        v.links.forEach(function (l, i) {
                            if (i) { td.appendChild(document.createTextNode(' · ')); }
                            td.appendChild(link(l.href, l.label));
                        });
                        td.className = 'fg-nowrap';
                    } else if (v.html) {
                        td.innerHTML = v.html; // uitvoer van de admin-kolomklasse zelf, zoals in het standaardgrid
                    } else {
                        td.textContent = v.text || '';
                    }
                } else {
                    td.textContent = v == null ? '' : String(v);
                }
                return td;
            }
            if (type === 'thumbnail') {
                if (v && cfg.mediaUrl) {
                    var img = document.createElement('img');
                    img.src = cfg.mediaUrl + v;
                    img.alt = '';
                    img.className = 'fg-thumb';
                    img.loading = 'lazy';
                    td.appendChild(img);
                }
                td.className = 'fg-center';
                return td;
            }
            td.textContent = v == null ? '' : v;
            if (type === 'money' || type === 'int') {
                td.className = 'fg-num';
            } else if (LONG_TYPES.indexOf(type) !== -1 && td.textContent.length > 40) {
                td.className = 'fg-long';
                td.title = td.textContent;
            } else if (type === 'address') {
                td.className = 'fg-long';
                td.title = td.textContent;
            }
            return td;
        }

        function render(data) {
            var keys = currentKeys();
            var frag = document.createDocumentFragment();
            data.items.forEach(function (item) {
                var tr = document.createElement('tr');
                var url = opts.rowUrl ? opts.rowUrl(item, cfg) : null;
                if (url) {
                    tr.setAttribute('data-url', url);
                    if (opts.rowTitle) { tr.title = opts.rowTitle(item, L); }
                }
                keys.forEach(function (key) { tr.appendChild(cellFor(key, item)); });
                frag.appendChild(tr);
            });
            body.textContent = '';
            body.appendChild(frag);

            var from = data.total ? (data.page - 1) * data.limit + 1 : 0;
            var to = Math.min(data.page * data.limit, data.total);
            var text = data.total.toLocaleString() + ' ' + (L.rows || '');
            if (data.total) { text += ', ' + from + '–' + to + ' ' + (L.shown || ''); }
            if (opts.summary) { text += opts.summary(data, L); }
            text += ' · ' + data.took_ms + ' ms';
            root.querySelector('#fg-summary').textContent = text;
            root.querySelector('#fg-summary-bottom').textContent = text;

            renderPager(root.querySelector('#fg-pager-top'), data);
            renderPager(root.querySelector('#fg-pager-bottom'), data);
            updateSortIndicators();
        }

        function renderPager(nav, data) {
            nav.textContent = '';
            function btn(label, page, disabled, current) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = label;
                b.disabled = !!disabled;
                if (current) { b.className = 'fg-current'; }
                b.addEventListener('click', function () { state.page = page; load(); });
                nav.appendChild(b);
            }
            btn('‹', data.page - 1, data.page <= 1);
            var pages = [];
            var p = data.pages;
            [1, 2, data.page - 2, data.page - 1, data.page, data.page + 1, data.page + 2, p - 1, p].forEach(function (n) {
                if (n >= 1 && n <= p && pages.indexOf(n) === -1) { pages.push(n); }
            });
            pages.sort(function (a, b) { return a - b; });
            var prev = 0;
            pages.forEach(function (n) {
                if (n - prev > 1) {
                    var gap = document.createElement('span');
                    gap.textContent = '…';
                    nav.appendChild(gap);
                }
                btn(String(n), n, false, n === data.page);
                prev = n;
            });
            btn('›', data.page + 1, data.page >= p);
        }

        function updateSortIndicators() {
            headers.forEach(function (th) {
                var ind = th.querySelector('.fg-sort-ind');
                if (!ind) { return; }
                ind.textContent = th.getAttribute('data-sort') === state.sort ? (state.dir === 'asc' ? ' ▲' : ' ▼') : '';
            });
        }

        /* ---------- data ophalen ---------- */
        function buildQuery() {
            var params = new URLSearchParams();
            params.set('page', state.page);
            params.set('limit', state.limit);
            params.set('sort', state.sort);
            params.set('dir', state.dir);
            params.set('form_key', cfg.formKey);
            Object.keys(state.filters).forEach(function (k) {
                var v = state.filters[k];
                if (Array.isArray(v)) { v.forEach(function (x) { params.append(k + '[]', x); }); }
                else { params.set(k, v); }
            });
            return params.toString();
        }

        function load() {
            var seq = ++requestSeq;
            root.classList.add('fg-loading');
            saveState();
            fetch(cfg.dataUrl + '?' + buildQuery(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) {
                    // Verlopen sessie of geen rechten: Magento stuurt dan een HTML-pagina i.p.v. JSON
                    if ((r.headers.get('Content-Type') || '').indexOf('json') === -1) {
                        throw new Error(L.reload || 'Sessie verlopen of geen rechten; herlaad de pagina.');
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (seq !== requestSeq) { return; }
                    if (data.error) { throw new Error(data.error); }
                    render(data);
                })
                .catch(function (e) {
                    root.querySelector('#fg-summary').textContent = (L.loadError || 'Fout bij laden') + ': ' + e.message;
                })
                .then(function () { if (seq === requestSeq) { root.classList.remove('fg-loading'); } });
        }

        /* ---------- rij-klik ---------- */
        var exclude = opts.rowClickExclude || [];
        function rowUrl(e) {
            if (e.target.closest('a, button, input, select, label')) { return null; }
            var td = e.target.closest('td[data-col]');
            if (td && exclude.indexOf(td.getAttribute('data-col')) !== -1) { return null; }
            var tr = e.target.closest('tr[data-url]');
            return tr ? tr.getAttribute('data-url') : null;
        }
        body.addEventListener('click', function (e) {
            var url = rowUrl(e);
            if (!url) { return; }
            if (e.ctrlKey || e.metaKey) { window.open(url, '_blank'); } else { window.location.href = url; }
        });
        body.addEventListener('auxclick', function (e) {
            if (e.button !== 1) { return; }
            var url = rowUrl(e);
            if (url) { e.preventDefault(); window.open(url, '_blank'); }
        });

        /* ---------- events ---------- */
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            state.filters = readFilters();
            state.page = 1;
            load();
        });
        var debounce;
        form.querySelectorAll('input[type="search"], input[type="text"]').forEach(function (el) {
            el.addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () { state.filters = readFilters(); state.page = 1; load(); }, 350);
            });
        });
        form.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach(function (el) {
            el.addEventListener('change', function () {
                state.filters = readFilters();
                state.page = 1;
                updateDropdownLabels();
                load();
            });
        });
        root.querySelector('#fg-reset').addEventListener('click', function () {
            form.reset();
            state.filters = {};
            state.page = 1;
            updateDropdownLabels();
            load();
        });
        limitSel.addEventListener('change', function () {
            state.limit = parseInt(limitSel.value, 10) || cfg.defaultLimit;
            state.page = 1;
            load();
        });
        headers.forEach(function (th) {
            var sortKey = th.getAttribute('data-sort');
            if (!sortKey) { return; }
            th.addEventListener('click', function () {
                if (state.sort === sortKey) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sort = sortKey;
                    state.dir = descByDefault.indexOf(sortKey) !== -1 ? 'desc' : 'asc';
                }
                state.page = 1;
                load();
            });
        });
        root.querySelectorAll('input[data-col-toggle]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var key = cb.getAttribute('data-col-toggle');
                if (cb.checked) { delete state.hiddenCols[key]; } else { state.hiddenCols[key] = true; }
                applyColumns();
                saveState();
            });
        });

        /* ---------- start ---------- */
        loadState();
        applyColumnOrder();
        writeFilters(state.filters);
        limitSel.value = String(state.limit);
        if (limitSel.value !== String(state.limit)) { state.limit = cfg.defaultLimit; limitSel.value = String(state.limit); }
        applyColumns();
        load();
    }

    window.LuckyFastGrid = {
        init: function (opts) { ready(function () { init(opts); }); }
    };
})();
