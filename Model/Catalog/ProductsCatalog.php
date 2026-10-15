<?php
declare(strict_types=1);

namespace Lucky\FastGrids\Model\Catalog;

use Lucky\FastGrids\Model\Config\GridColumns;
use Lucky\FastGrids\Model\SchemaInfo;
use Magento\Framework\App\ResourceConnection;

/**
 * Kolomcatalogus van het productgrid: kolommen van catalog_product_entity, voorraad,
 * websites en EAV-attributen (de kernset + alle attributen met "Add to Column Options").
 * Elk EAV-attribuut is één LEFT JOIN op zijn backend-tabel (store 0).
 */
class ProductsCatalog extends AbstractCatalog
{
    public const GRID = 'products';
    public const TABLE = 'catalog_product_entity';

    /** EAV-attributen die altijd worden aangeboden, ook als "in grid" uitstaat. */
    private const CORE_ATTRIBUTES = ['name', 'price', 'status', 'visibility', 'thumbnail', 'special_price', 'weight', 'url_key', 'image', 'small_image'];

    private const ENTITY = [
        'entity_id'        => ['ID', 'int'],
        'sku'              => ['SKU', 'text'],
        'type_id'          => ['Type', 'product_type'],
        'attribute_set_id' => ['Attributenset', 'attribute_set'],
        'created_at'       => ['Aangemaakt', 'datetime'],
        'updated_at'       => ['Gewijzigd', 'datetime'],
    ];

    private const LABELS_NL = [
        'name' => 'Naam', 'price' => 'Prijs', 'status' => 'Status', 'visibility' => 'Zichtbaarheid',
        'thumbnail' => 'Afbeelding', 'special_price' => 'Actieprijs', 'weight' => 'Gewicht', 'url_key' => 'URL-sleutel',
    ];

    private ?array $attributes = null;

    public function __construct(
        GridColumns $gridColumns,
        private SchemaInfo $schema,
        private ResourceConnection $resource
    ) {
        parent::__construct($gridColumns);
    }

    public function uiComponent(): string
    {
        return 'product_listing';
    }

    protected function builtinDefaults(): array
    {
        return ['entity_id', 'thumbnail', 'name', 'type_id', 'attribute_set_id', 'sku', 'price', 'qty', 'visibility', 'status', 'websites', 'actions'];
    }

    /**
     * EAV-attributen die als kolom beschikbaar zijn.
     * @return array<string, array{id:int,backend:string,input:string,label:string}>
     */
    public function attributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }
        $this->attributes = [];
        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from(['a' => $this->resource->getTableName('eav_attribute')], ['attribute_id', 'attribute_code', 'frontend_label', 'frontend_input', 'backend_type'])
            ->join(['c' => $this->resource->getTableName('catalog_eav_attribute')], 'c.attribute_id = a.attribute_id', ['is_used_in_grid'])
            ->join(['t' => $this->resource->getTableName('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
            ->where('t.entity_type_code = ?', 'catalog_product')
            ->where('a.backend_type IN (?)', ['varchar', 'int', 'decimal', 'text', 'datetime'])
            ->order('a.frontend_label ASC');
        foreach ($conn->fetchAll($select) as $r) {
            $code = (string) $r['attribute_code'];
            if (!(int) $r['is_used_in_grid'] && !in_array($code, self::CORE_ATTRIBUTES, true)) {
                continue;
            }
            $this->attributes[$code] = [
                'id'      => (int) $r['attribute_id'],
                'backend' => (string) $r['backend_type'],
                'input'   => (string) ($r['frontend_input'] ?: 'text'),
                'label'   => self::LABELS_NL[$code] ?? (string) ($r['frontend_label'] ?: $code),
            ];
        }
        return $this->attributes;
    }

    protected function build(): array
    {
        $items = [];
        foreach (self::ENTITY as $col => [$label, $type]) {
            if ($this->schema->hasColumn(self::TABLE, $col)) {
                $items[$col] = ['label' => $label, 'type' => $type, 'sort' => true, 'src' => 'grid', 'col' => $col];
            }
        }
        // Kernattributen eerst in een logische volgorde, daarna de rest alfabetisch
        $attrs = $this->attributes();
        $ordered = [];
        foreach (self::CORE_ATTRIBUTES as $code) {
            if (isset($attrs[$code])) {
                $ordered[$code] = $attrs[$code];
            }
        }
        $ordered += $attrs;
        foreach ($ordered as $code => $meta) {
            if (isset($items[$code])) {
                continue;
            }
            $items[$code] = [
                'label' => $meta['label'],
                'type'  => $this->typeFor($code, $meta),
                'sort'  => !in_array($meta['input'], ['textarea', 'media_image', 'multiselect'], true),
                'src'   => 'join', 'join' => 'eav:' . $code, 'col' => $code,
            ];
        }
        if ($this->schema->hasColumns('cataloginventory_stock_item', ['product_id', 'qty', 'is_in_stock'])) {
            $items['qty'] = ['label' => 'Aantal', 'type' => 'int', 'sort' => true, 'src' => 'join', 'join' => 'stock', 'col' => 'qty'];
            $items['is_in_stock'] = ['label' => 'Op voorraad', 'type' => 'bool', 'sort' => true, 'src' => 'join', 'join' => 'stock', 'col' => 'is_in_stock'];
        }
        if ($this->schema->hasColumns('catalog_product_website', ['product_id', 'website_id'])) {
            $items['websites'] = ['label' => 'Websites', 'type' => 'websites', 'sort' => false, 'src' => 'join', 'join' => 'websites', 'col' => 'websites'];
        }
        $items['actions'] = ['label' => 'Acties', 'type' => 'text', 'sort' => false, 'src' => 'pseudo'];
        return $items;
    }

    private function typeFor(string $code, array $meta): string
    {
        return match ($meta['input']) {
            'price' => 'money',
            'boolean' => 'bool',
            'select' => 'option:catalog_product:' . $code,
            'multiselect' => 'multioption:catalog_product:' . $code,
            'date' => 'date',
            'media_image' => 'thumbnail',
            'weight' => 'text',
            default => $meta['backend'] === 'decimal' ? 'money' : ($meta['backend'] === 'datetime' ? 'datetime' : 'text'),
        };
    }
}
