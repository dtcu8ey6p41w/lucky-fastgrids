<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

use Lucky\FastGrids\Model\Config\GridColumns;
use Lucky\FastGrids\Model\SchemaInfo;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Kolomcatalogus van het klantengrid: alle kolommen van customer_grid_flat (incl. klantattributen
 * met "in grid"), plus Acties, Inloggen als klant en Mailchimp-status (als aanwezig).
 */
class CustomersCatalog extends AbstractCatalog
{
    public const GRID = 'customers';
    public const TABLE = 'customer_grid_flat';
    public const MAILCHIMP_TABLE = 'mailchimp_sync_ecommerce';

    private const HIDDEN = ['billing_region_id'];

    private const KNOWN = [
        'entity_id'          => ['ID', 'int'],
        'name'               => ['Naam', 'text'],
        'email'              => ['E-mail', 'text'],
        'group_id'           => ['Groep', 'customer_group'],
        'created_at'         => ['Klant sinds', 'datetime'],
        'website_id'         => ['Website', 'website'],
        'confirmation'       => ['Bevestigd', 'confirmation'],
        'created_in'         => ['Aangemaakt in', 'text'],
        'dob'                => ['Geboortedatum', 'date'],
        'gender'             => ['Geslacht', 'option:customer:gender'],
        'taxvat'             => ['BTW-nummer', 'text'],
        'lock_expires'       => ['Vergrendeld tot', 'datetime'],
        'shipping_full'      => ['Verzendadres', 'text'],
        'billing_full'       => ['Factuuradres', 'text'],
        'billing_firstname'  => ['Voornaam (factuur)', 'text'],
        'billing_lastname'   => ['Achternaam (factuur)', 'text'],
        'billing_telephone'  => ['Telefoon', 'text'],
        'billing_postcode'   => ['Postcode', 'text'],
        'billing_country_id' => ['Land', 'country'],
        'billing_region'     => ['Regio', 'text'],
        'billing_street'     => ['Straat', 'text'],
        'billing_city'       => ['Plaats', 'text'],
        'billing_fax'        => ['Fax', 'text'],
        'billing_vat_id'     => ['VAT ID', 'text'],
        'billing_company'    => ['Bedrijf', 'text'],
    ];

    private const NOT_SORTABLE = ['shipping_full', 'billing_full', 'billing_fax'];

    private ?array $attributeMeta = null;

    public function __construct(
        GridColumns $gridColumns,
        private SchemaInfo $schema,
        private ResourceConnection $resource,
        private ScopeConfigInterface $scopeConfig,
        private ModuleManager $moduleManager,
        private AuthorizationInterface $authorization
    ) {
        parent::__construct($gridColumns);
    }

    public function uiComponent(): string
    {
        return 'customer_listing';
    }

    protected function builtinDefaults(): array
    {
        return [
            'entity_id', 'group_id', 'name', 'email', 'billing_telephone', 'billing_postcode', 'created_at',
            'created_in', 'dob', 'shipping_full', 'email_cc', 'actions', 'klantinformatie', 'login_as_customer',
            'klant_type', 'klanttype', 'customer_type', 'dropdown',
        ];
    }

    public function hasMailchimp(): bool
    {
        return $this->scopeConfig->isSetFlag('mailchimp/general/active')
            && (string) $this->scopeConfig->getValue('mailchimp/general/monkeystore') !== ''
            && $this->schema->hasColumns(self::MAILCHIMP_TABLE, ['related_id', 'type', 'mailchimp_store_id', 'mailchimp_sent']);
    }

    public function isLoginAsCustomerAllowed(): bool
    {
        return $this->moduleManager->isEnabled('Magento_LoginAsCustomerAdminUi')
            && $this->scopeConfig->isSetFlag('login_as_customer/general/enabled')
            && $this->authorization->isAllowed('Magento_LoginAsCustomer::login');
    }

    protected function build(): array
    {
        $items = [];
        $describe = $this->schema->tableExists(self::TABLE)
            ? $this->resource->getConnection()->describeTable($this->resource->getTableName(self::TABLE))
            : [];
        $meta = $this->attributeMeta();
        foreach ($describe as $col => $info) {
            if (in_array($col, self::HIDDEN, true)) {
                continue;
            }
            if (isset(self::KNOWN[$col])) {
                [$label, $type] = self::KNOWN[$col];
            } elseif (isset($meta[$col])) {
                $label = $meta[$col]['label'];
                $type = $this->typeFromInput($meta[$col]['input'], $col);
            } else {
                $label = $this->humanize($col);
                $type = $this->typeFromDb((string) $info['DATA_TYPE']);
            }
            $items[$col] = [
                'label' => $label, 'type' => $type,
                'sort' => !in_array($col, self::NOT_SORTABLE, true) && !in_array($type, ['text'], true) || in_array($col, ['name', 'email', 'created_in', 'taxvat', 'billing_telephone', 'billing_postcode', 'billing_city', 'billing_company', 'billing_region', 'billing_street', 'billing_firstname', 'billing_lastname'], true) || ($type === 'text' && isset($meta[$col]) && $meta[$col]['input'] === 'text'),
                'src' => 'grid', 'col' => $col,
            ];
        }
        $items['actions'] = ['label' => 'Acties', 'type' => 'text', 'sort' => false, 'src' => 'pseudo'];
        if ($this->isLoginAsCustomerAllowed()) {
            $items['login_as_customer'] = ['label' => 'Inloggen als klant', 'type' => 'text', 'sort' => false, 'src' => 'pseudo'];
        }
        if ($this->hasMailchimp()) {
            $items['mailchimp'] = ['label' => 'Mailchimp', 'type' => 'mailchimp', 'sort' => true, 'src' => 'join', 'join' => 'mailchimp', 'col' => 'mailchimp_sent', 'ui' => 'mailchimp_sync'];
        }
        return $items;
    }

    /** @return array<string, array{label:string,input:string}> alle klantattributen */
    public function attributeMeta(): array
    {
        if ($this->attributeMeta !== null) {
            return $this->attributeMeta;
        }
        $this->attributeMeta = [];
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from(['a' => $this->resource->getTableName('eav_attribute')], ['attribute_code', 'frontend_label', 'frontend_input'])
            ->join(['t' => $this->resource->getTableName('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
            ->where('t.entity_type_code = ?', 'customer');
        foreach ($conn->fetchAll($select) as $r) {
            $this->attributeMeta[(string) $r['attribute_code']] = [
                'label' => (string) ($r['frontend_label'] ?: $r['attribute_code']),
                'input' => (string) ($r['frontend_input'] ?: 'text'),
            ];
        }
        return $this->attributeMeta;
    }

    private function typeFromInput(string $input, string $code): string
    {
        return match ($input) {
            'boolean' => 'bool',
            'select' => 'option:customer:' . $code,
            'multiselect' => 'multioption:customer:' . $code,
            'date' => 'date',
            default => 'text',
        };
    }

    private function typeFromDb(string $dbType): string
    {
        $dbType = strtolower($dbType);
        return match (true) {
            in_array($dbType, ['timestamp', 'datetime'], true) => 'datetime',
            $dbType === 'date' => 'date',
            $dbType === 'decimal' => 'money',
            $dbType === 'tinyint' => 'bool',
            in_array($dbType, ['int', 'smallint', 'bigint'], true) => 'int',
            default => 'text',
        };
    }
}
