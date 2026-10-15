/**
 * Lucky_FastGrids — Products Fast (adapter op grid.js).
 */
(function () {
    'use strict';

    window.LuckyFastGrid.init({
        rootId: 'fastgrids-products',
        storageKey: 'lucky_fastgrids_products_v1',
        defaultSort: 'entity_id',
        rowClickExclude: ['actions'],

        rowUrl: function (item, cfg) { return cfg.editUrl.replace('__ID__', String(item.entity_id)); },
        rowTitle: function (item, L) { return (L.openRow || 'Open product') + ' ' + (item._name || item._sku); },

        cellFor: function (td, key, v, item, api) {
            var cfg = api.cfg, L = api.labels;
            switch (key) {
                case 'entity_id':
                case 'name':
                case 'sku':
                    td.appendChild(api.link(cfg.editUrl.replace('__ID__', String(item.entity_id)), String(v == null ? '' : v), 'fg-row-link'));
                    return true;
                case 'status':
                    td.appendChild(api.badge(v, item._status === 1 ? 'fg-status-complete' : 'fg-status-canceled'));
                    return true;
                case 'actions':
                    td.appendChild(api.link(cfg.editUrl.replace('__ID__', String(item.entity_id)), L.edit || 'Bewerken'));
                    td.className = 'fg-nowrap';
                    return true;
                default:
                    return false;
            }
        }
    });
})();
