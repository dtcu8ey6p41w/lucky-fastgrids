<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Block\Adminhtml;

use Lucky\FastGrids\Model\Catalog\CustomersCatalog;
use Lucky\FastGrids\Model\Customers\CustomerList;
use Lucky\FastGrids\Model\Lookups;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Levert de template de kolomdefinities (uit de configuratie), filteropties en een JSON-config.
 */
class Customers extends Template
{
    public function __construct(
        Context $context,
        private CustomersCatalog $catalog,
        private Lookups $lookups,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<string, array{0:string,1:?string,2:bool}> */
    public function getColumns(): array
    {
        $out = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $out[$key] = [(string) $def['label'], $def['sort'] ? $key : null, true];
        }
        return $out;
    }

    /** @return array<int,string> */
    public function getGroupOptions(): array
    {
        $groups = $this->lookups->customerGroups();
        unset($groups['0']);
        asort($groups);
        return $groups;
    }

    public function isLoginAsCustomerAllowed(): bool
    {
        return $this->catalog->isLoginAsCustomerAllowed();
    }

    public function getConfigJson(): string
    {
        $types = [];
        foreach ($this->catalog->selected() as $key => $def) {
            $types[$key] = $def['type'];
        }
        return json_encode([
            'dataUrl'      => $this->getUrl('fastgrids/customers/data'),
            'editUrl'      => $this->getUrl('customer/index/edit', ['id' => '__ID__']),
            'ordersUrl'    => $this->getUrl('fastgrids/orders/index'),
            'loginUrl'     => $this->isLoginAsCustomerAllowed() ? $this->getUrl('loginascustomer/login/login') : '',
            'loginAllowed' => $this->isLoginAsCustomerAllowed(),
            'defaultLimit' => CustomerList::DEFAULT_LIMIT,
            'formKey'      => $this->getFormKey(),
            'colTypes'     => $types,
            'descByDefault'=> ['created_at', 'entity_id', 'dob', 'lock_expires'],
            'labels'       => [
                'rows' => (string) __('klanten'), 'shown' => (string) __('getoond'), 'openRow' => (string) __('Open klant'),
                'login' => (string) __('Inloggen als klant'), 'loginFail' => (string) __('Inloggen als klant is mislukt.'),
                'edit' => (string) __('Bewerken'), 'orders' => (string) __('Orders'),
                'loadError' => (string) __('Fout bij laden'), 'chosen' => (string) __('gekozen'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
