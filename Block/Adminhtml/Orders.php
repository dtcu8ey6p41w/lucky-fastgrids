<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml;

use Lucky\FastGrids\Model\Catalog\OrdersCatalog;
use Lucky\FastGrids\Model\Lookups;
use Lucky\FastGrids\Model\Orders\OrderList;
use Lucky\FastGrids\Model\Orders\UsedValues;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Levert de template de kolomdefinities (uit de configuratie), filteropties en een JSON-config.
 */
class Orders extends Template
{
    public function __construct(
        Context $context,
        private OrdersCatalog $catalog,
        private Lookups $lookups,
        private UsedValues $usedValues,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<string, array{0:string,1:?string,2:bool}> sleutel => [label, sorteersleutel, zichtbaar] */
    public function getColumns(): array
    {
        $out = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $out[$key] = [(string) $def['label'], $def['sort'] ? $key : null, true];
        }
        return $out;
    }

    public function hasDeliveryDate(): bool
    {
        return $this->catalog->hasDeliveryDate();
    }

    /** @return array<string,string> status => label; alleen statussen die in orders voorkomen */
    public function getStatusOptions(): array
    {
        $labels = $this->lookups->orderStatuses();
        $options = [];
        foreach ($this->usedValues->statuses() as $code) {
            $options[$code] = (string) ($labels[$code] ?? $code);
        }
        asort($options);
        return $options;
    }

    /** Betaalmethode-filter alleen als de kolom Betaalmethode is aangevinkt in de instellingen. */
    public function hasPaymentFilter(): bool
    {
        return isset($this->catalog->selected()['payment_method']);
    }

    /** @return array<string,string> code => titel; alleen methoden die in orders voorkomen */
    public function getPaymentOptions(): array
    {
        $titles = $this->lookups->paymentTitles();
        $options = [];
        foreach ($this->usedValues->paymentMethods() as $code) {
            $options[$code] = (string) ($titles[$code] ?? $code);
        }
        asort($options);
        return $options;
    }

    /** @return array<string,string> group_id => naam, 'Gast' eerst */
    public function getGroupOptions(): array
    {
        $groups = $this->lookups->customerGroups();
        $gast = $groups['0'] ?? (string) __('Gast');
        unset($groups['0']);
        asort($groups);
        return ['0' => $gast] + $groups;
    }

    public function getConfigJson(): string
    {
        $types = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $types[$key] = $def['type'];
        }
        return json_encode([
            'dataUrl'      => $this->getUrl('fastgrids/orders/data'),
            'viewUrl'      => $this->getUrl('sales/order/view', ['order_id' => '__ID__']),
            'defaultLimit' => OrderList::DEFAULT_LIMIT,
            'formKey'      => $this->getFormKey(),
            'colTypes'     => $types,
            'descByDefault'=> ['created_at', 'updated_at', 'delivery_date'],
            'labels'       => [
                'rows' => (string) __('orders'), 'shown' => (string) __('getoond'), 'total' => (string) __('totaal (incl. geannuleerd)'),
                'openRow' => (string) __('Open order'), 'loadError' => (string) __('Fout bij laden'), 'reload' => (string) __('Sessie verlopen of geen rechten; herlaad de pagina.'), 'chosen' => (string) __('gekozen'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
