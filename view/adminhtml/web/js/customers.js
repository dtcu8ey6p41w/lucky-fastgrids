/**
 * Lucky_FastGrids — Customers Fast (adapter op grid.js).
 */
(function () {
    'use strict';

    function loginAsCustomer(cfg, L, customerId) {
        // Tab synchroon openen (binnen de klik), anders blokkeert de browser de popup.
        var win = window.open('', '_blank');
        var params = new URLSearchParams();
        params.set('customer_id', customerId);
        params.set('form_key', cfg.formKey);
        fetch(cfg.loginUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: params.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.redirectUrl) {
                    win.location.href = data.redirectUrl;
                } else {
                    win.close();
                    var msg = (data.messages || []).map(function (m) { return m.message || m; }).join('\n');
                    alert(msg || L.loginFail || 'Inloggen als klant is mislukt.');
                }
            })
            .catch(function (e) { win.close(); alert((L.loginFail || 'Inloggen als klant is mislukt.') + ' ' + e.message); });
    }

    window.LuckyFastGrid.init({
        rootId: 'fastgrids-customers',
        storageKey: 'lucky_fastgrids_customers_v3',
        defaultSort: 'entity_id',
        rowClickExclude: ['login_as_customer', 'actions'],

        rowUrl: function (item, cfg) { return cfg.editUrl.replace('__ID__', String(item.entity_id)); },
        rowTitle: function (item, L) { return (L.openRow || 'Open klant') + ' ' + item._name; },

        cellFor: function (td, key, v, item, api) {
            var cfg = api.cfg, L = api.labels;
            switch (key) {
                case 'entity_id':
                case 'name':
                    td.appendChild(api.link(cfg.editUrl.replace('__ID__', String(item.entity_id)), String(v), 'fg-row-link'));
                    return true;
                case 'confirmation':
                    td.appendChild(api.badge(v, item._confirmed ? 'fg-status-complete' : 'fg-status-pending'));
                    return true;
                case 'mailchimp':
                    if (v) {
                        var cls = item._mailchimp_code === 1 ? 'fg-status-complete' : (item._mailchimp_code === 4 ? 'fg-status-canceled' : 'fg-status-pending');
                        td.appendChild(api.badge(v, cls, item._mailchimp_error || ''));
                    }
                    return true;
                case 'login_as_customer':
                    if (cfg.loginAllowed) {
                        var l = api.link('#', L.login || 'Inloggen als klant');
                        l.addEventListener('click', function (e) { e.preventDefault(); loginAsCustomer(cfg, L, item.entity_id); });
                        td.appendChild(l);
                    }
                    return true;
                case 'actions':
                    td.appendChild(api.link(cfg.editUrl.replace('__ID__', String(item.entity_id)), L.edit || 'Bewerken'));
                    td.appendChild(document.createTextNode(' · '));
                    td.appendChild(api.link(cfg.ordersUrl + '#q=' + encodeURIComponent(item._email), L.orders || 'Orders'));
                    td.className = 'fg-nowrap';
                    return true;
                default:
                    return false;
            }
        }
    });
})();
