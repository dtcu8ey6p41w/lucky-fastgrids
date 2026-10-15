<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

use Lucky\FastGrids\Model\Config\GridColumns;
use Lucky\FastGrids\Model\SchemaInfo;
use Magento\Framework\App\ResourceConnection;

/**
 * Kolomcatalogus van het ordergrid: alle kolommen van sales_order_grid, de bezorgdatum/-tijd/-opmerking
 * (Amasty, als aanwezig), de verzendmethode-code (sales_order) en klantattributen van de klant van de
 * order (via customer_grid_flat). Overige kolommen van het standaardgrid komen als "plugin"-kolom.
 */
class OrdersCatalog extends AbstractCatalog
{
    public const GRID = 'orders';
    public const TABLE = 'sales_order_grid';
    public const DELIVERY_TABLE = 'amasty_deliverydate_deliverydate_order';
    public const CUSTOMER_TABLE = 'customer_grid_flat';
    public const ORDER_TABLE = 'sales_order';

    private const HIDDEN = ['entity_id', 'customer_id', 'order_currency_code', 'base_currency_code'];

    private const KNOWN = [
        'increment_id'          => ['ID', 'text'],
        'created_at'            => ['Besteldatum', 'datetime'],
        'updated_at'            => ['Gewijzigd', 'datetime'],
        'status'                => ['Status', 'order_status'],
        'store_id'              => ['Winkel', 'store'],
        'store_name'            => ['Winkelnaam', 'text'],
        'customer_email'        => ['E-mail', 'text'],
        'customer_name'         => ['Klantnaam', 'text'],
        'customer_group'        => ['Klantgroep', 'customer_group'],
        'billing_name'          => ['Factuurnaam', 'text'],
        'shipping_name'         => ['Verzendnaam', 'text'],
        'billing_address'       => ['Factuuradres', 'address'],
        'shipping_address'      => ['Verzendadres', 'address'],
        'shipping_information'  => ['Verzending', 'text'],
        'payment_method'        => ['Betaalmethode', 'payment'],
        'grand_total'           => ['Totaal', 'money'],
        'base_grand_total'      => ['Totaal (basis)', 'money'],
        'total_paid'            => ['Betaald', 'money'],
        'base_total_paid'       => ['Betaald (basis)', 'money'],
        'total_refunded'        => ['Gecrediteerd', 'money'],
        'subtotal'              => ['Subtotaal', 'money'],
        'shipping_and_handling' => ['Verzendkosten', 'money'],
        'pickup_location_code'  => ['Afhaalpunt', 'text'],
    ];

    private const SORTABLE_TYPES = ['text', 'datetime', 'date', 'money', 'int', 'order_status', 'customer_group', 'store', 'payment', 'bool'];

    public function __construct(
        GridColumns $gridColumns,
        private SchemaInfo $schema,
        private ResourceConnection $resource
    ) {
        parent::__construct($gridColumns);
    }

    public function uiComponent(): string
    {
        return 'sales_order_grid';
    }

    protected function builtinDefaults(): array
    {
        return ['increment_id', 'created_at', 'status', 'shipping_name', 'shipping_address', 'customer_group', 'delivery_date', 'customer_klantinformatie'];
    }

    public function hasDeliveryDate(): bool
    {
        return $this->schema->hasColumns(self::DELIVERY_TABLE, ['order_id', 'date']);
    }

    protected function build(): array
    {
        $items = [];
        $describe = $this->schema->tableExists(self::TABLE)
            ? $this->resource->getConnection()->describeTable($this->resource->getTableName(self::TABLE))
            : [];
        foreach ($describe as $col => $meta) {
            if (in_array($col, self::HIDDEN, true)) {
                continue;
            }
            [$label, $type] = self::KNOWN[$col] ?? [$this->humanize($col), $this->typeFromDb((string) $meta['DATA_TYPE'])];
            $items[$col] = ['label' => $label, 'type' => $type, 'sort' => in_array($type, self::SORTABLE_TYPES, true), 'src' => 'grid', 'col' => $col];
        }
        if ($this->hasDeliveryDate()) {
            $items['delivery_date'] = ['label' => 'Bezorgdatum', 'type' => 'date', 'sort' => true, 'src' => 'join', 'join' => 'delivery', 'col' => 'date', 'ui' => 'amasty_deliverydate_date'];
            foreach (['time_from' => ['Bezorgtijd van', 'text'], 'time_to' => ['Bezorgtijd tot', 'text'], 'comment' => ['Bezorgopmerking', 'text']] as $col => [$label, $type]) {
                if ($this->schema->hasColumn(self::DELIVERY_TABLE, $col)) {
                    $items['amasty_deliverydate_' . $col] = ['label' => $label, 'type' => $type, 'sort' => $col !== 'comment', 'src' => 'join', 'join' => 'delivery', 'col' => $col];
                }
            }
        }
        if ($this->schema->hasColumn(self::ORDER_TABLE, 'shipping_method')) {
            $items['shipping_method'] = ['label' => 'Verzendmethode (code)', 'type' => 'text', 'sort' => true, 'src' => 'join', 'join' => 'order', 'col' => 'shipping_method'];
        }
        foreach ($this->customerAttributeColumns() as $code => $label) {
            $items['customer_' . $code] = ['label' => $label, 'type' => 'text', 'sort' => false, 'src' => 'join', 'join' => 'customer', 'col' => $code];
        }
        return $items;
    }

    /** @return array<string,string> code => label */
    private function customerAttributeColumns(): array
    {
        $gridColumns = $this->schema->columns(self::CUSTOMER_TABLE);
        if (!$gridColumns) {
            return [];
        }
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from(['a' => $this->resource->getTableName('eav_attribute')], ['attribute_code', 'frontend_label'])
            ->join(['c' => $this->resource->getTableName('customer_eav_attribute')], 'c.attribute_id = a.attribute_id', [])
            ->join(['t' => $this->resource->getTableName('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
            ->where('t.entity_type_code = ?', 'customer')
            ->where('c.is_used_in_grid = 1')
            ->where('a.is_user_defined = 1')
            ->order('c.sort_order ASC');
        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $code = (string) $r['attribute_code'];
            if (in_array($code, $gridColumns, true)) {
                $out[$code] = (string) ($r['frontend_label'] ?: $code);
            }
        }
        return $out;
    }

    private function typeFromDb(string $dbType): string
    {
        $dbType = strtolower($dbType);
        return match (true) {
            in_array($dbType, ['timestamp', 'datetime'], true) => 'datetime',
            $dbType === 'date' => 'date',
            $dbType === 'decimal' => 'money',
            $dbType === 'tinyint' => 'bool',
            in_array($dbType, ['int', 'smallint', 'bigint', 'mediumint'], true) => 'int',
            default => 'text',
        };
    }
}
