/**
 * Lucky_FastGrids — Orders Fast (adapter op grid.js).
 */
(function () {
    'use strict';

    window.LuckyFastGrid.init({
        rootId: 'fastgrids-orders',
        storageKey: 'lucky_fastgrids_orders_v3',
        defaultSort: 'created_at',

        rowUrl: function (item, cfg) { return cfg.viewUrl.replace('__ID__', String(item.entity_id)); },
        rowTitle: function (item, L) { return (L.openRow || 'Open order') + ' ' + item._increment_id; },

        summary: function (data, L) { return ' · ' + (L.total || 'totaal') + ' ' + data.sum_formatted; },

        cellFor: function (td, key, v, item, api) {
            switch (key) {
                case 'increment_id':
                    td.appendChild(api.link(api.cfg.viewUrl.replace('__ID__', String(item.entity_id)), v, 'fg-row-link'));
                    return true;
                case 'status':
                    td.appendChild(api.badge(v, 'fg-status-' + item._status));
                    return true;
                default:
                    return false;
            }
        }
    });
})();
